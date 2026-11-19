<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Organization;
use App\Modules\Notifications\NotificationEvents;
use App\Modules\Notifications\NotificationService;
use App\Models\LeaveAccrualEntry;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LeaveService
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function chargeableDays(Employee $employee, string $startsOn, string $endsOn): string
    {
        $cursor = new DateTimeImmutable($startsOn);
        $end = new DateTimeImmutable($endsOn);
        $count = 0;
        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');
            $shift = $this->attendance->resolveShift($employee, $date);
            $weekday = (int) $cursor->format('N');
            $weeklyOff = $shift !== null && in_array($weekday, array_map('intval', $shift->weekly_offs ?? []), true);
            $holiday = Holiday::query()->whereDate('observed_on', $date)->where(function ($query) use ($employee): void {
                $query->whereNull('legal_entity_id')->orWhere('legal_entity_id', $employee->legal_entity_id);
            })->exists();
            if (! $weeklyOff && ! $holiday) {
                $count++;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return number_format($count, 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function apply(Employee $employee, array $input): LeaveApplication
    {
        $type = LeaveType::query()->find($input['leave_type_id']);
        $policy = LeavePolicy::query()->where('leave_type_id', $input['leave_type_id'])->where('year', (int) substr($input['starts_on'], 0, 4))->first();
        if ($type === null || $policy === null) {
            throw ValidationException::withMessages(['leave_type_id' => ['Choose a leave type that has a policy for that year.']]);
        }
        $days = $this->chargeableDays($employee, $input['starts_on'], $input['ends_on']);
        if ((float) $days <= 0) {
            throw ValidationException::withMessages(['starts_on' => ['The selected dates are weekly offs or holidays.']]);
        }
        if ($type->paid && (float) $this->balance($employee, $type, (int) $policy->year)->availableDays() < (float) $days) {
            throw ValidationException::withMessages(['days' => ['The leave balance does not cover this application.']]);
        }

        $application = DB::transaction(function () use ($employee, $type, $policy, $input, $days): LeaveApplication {
            if ($type->paid) {
                $balance = $this->balance($employee, $type, (int) $policy->year);
                $balance->used_days = number_format((float) $balance->used_days + (float) $days, 2, '.', '');
                $balance->save();
            }

            return LeaveApplication::query()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'starts_on' => $input['starts_on'],
                'ends_on' => $input['ends_on'],
                'days' => $days,
                'reason' => $input['reason'] ?? null,
                'status' => 'pending',
                'current_step' => $policy->approval_steps[0],
                'approval_steps' => $policy->approval_steps,
            ]);
        });
            $this->notifyLeave($employee, $application, NotificationEvents::LEAVE_APPLIED, 'Leave application', $employee->fullName().' applied for leave from '.$application->starts_on->toDateString().' to '.$application->ends_on->toDateString().'.');
        $organization = \App\Models\Organization::query()->find($employee->organization_id);
        if ($organization !== null) {
            app(\App\Modules\Saas\WorkflowRunner::class)->dispatch($organization, 'leave.applied', 'leave:'.$application->id);
        }

        return $application;
    }

    public function decide(LeaveApplication $application, User $actor, bool $approve): LeaveApplication
    {
        if ($application->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['This leave application is already decided.']]);
        }
        $employee = $application->employee()->firstOrFail();
        $this->attendance->assertCanDecide($actor, $employee, (string) $application->current_step, 'leave.approve');
        if (! $approve) {
            return DB::transaction(function () use ($application): LeaveApplication {
                $this->restore($application);
                $application->status = 'rejected';
                $application->current_step = null;
                $application->save();

                return $application;
            });
        }
        $steps = $application->approval_steps;
        $index = array_search($application->current_step, $steps, true);
        $next = is_int($index) ? ($steps[$index + 1] ?? null) : null;
        $application->current_step = $next;
        if ($next === null) {
            $application->status = 'approved';
            $application->save();
            $this->notifyLeave($employee, $application, NotificationEvents::LEAVE_APPROVED, 'Leave approved', 'Leave from '.$application->starts_on->toDateString().' to '.$application->ends_on->toDateString().' is approved.');
            $cursor = new DateTimeImmutable($application->starts_on->toDateString());
            $end = new DateTimeImmutable($application->ends_on->toDateString());
            while ($cursor <= $end) {
                $this->attendance->rebuild($employee, $cursor->format('Y-m-d'));
                $cursor = $cursor->modify('+1 day');
            }
        } else {
            $application->save();
        }

        return $application->refresh();
    }

    public function accrue(int $year, int $month): int
    {
        $count = 0;
        $policies = LeavePolicy::query()->where('year', $year)->get();
        $employees = Employee::query()->where('status', 'active')->get();
        foreach ($employees as $employee) {
            foreach ($policies as $policy) {
                $exists = LeaveAccrualEntry::query()
                    ->where('employee_id', $employee->id)
                    ->where('leave_type_id', $policy->leave_type_id)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->exists();
                if ($exists) {
                    continue;
                }
                $balance = $this->balance($employee, LeaveType::query()->findOrFail($policy->leave_type_id), $year);
                $granted = (float) $balance->opening_days + (float) $balance->accrued_days + (float) $balance->carried_days;
                $add = min((float) $policy->accrual_days, max(0, (float) $policy->annual_days - $granted));
                $balance->accrued_days = number_format((float) $balance->accrued_days + $add, 2, '.', '');
                $balance->save();
                LeaveAccrualEntry::query()->create([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $policy->leave_type_id,
                    'year' => $year,
                    'month' => $month,
                    'days' => number_format($add, 2, '.', ''),
                ]);
                $count++;
            }
        }

        return $count;
    }

    public function carryForward(int $fromYear, int $toYear): int
    {
        $count = 0;
        $balances = LeaveBalance::query()->where('year', $fromYear)->get();
        foreach ($balances as $balance) {
            $policy = LeavePolicy::query()->where('leave_type_id', $balance->leave_type_id)->where('year', $fromYear)->first();
            $employee = Employee::query()->find($balance->employee_id);
            $type = LeaveType::query()->find($balance->leave_type_id);
            if ($policy === null || $employee === null || $type === null) {
                continue;
            }
            $carry = min((float) $balance->availableDays(), (float) $policy->carry_forward_max);
            $next = $this->balance($employee, $type, $toYear);
            $next->carried_days = number_format($carry, 2, '.', '');
            $next->save();
            $count++;
        }

        return $count;
    }

    public function encash(Employee $employee, LeaveType $type, int $year, float $days): LeaveBalance
    {
        $policy = LeavePolicy::query()->where('leave_type_id', $type->id)->where('year', $year)->first();
        if ($policy === null || ! $policy->encashment_allowed) {
            throw ValidationException::withMessages(['leave_type_id' => ['Encashment is not configured for this leave type.']]);
        }
        if ($days <= 0 || $days > (float) $policy->encashment_max) {
            throw ValidationException::withMessages(['days' => ['The days exceed the encashment limit.']]);
        }
        $balance = $this->balance($employee, $type, $year);
        if ($days > (float) $balance->availableDays()) {
            throw ValidationException::withMessages(['days' => ['The leave balance does not cover this encashment.']]);
        }
        $balance->encashed_days = number_format((float) $balance->encashed_days + $days, 2, '.', '');
        $balance->save();

        return $balance;
    }

    public function balance(Employee $employee, LeaveType $type, int $year): LeaveBalance
    {
        return LeaveBalance::query()->firstOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
            ['opening_days' => 0, 'accrued_days' => 0, 'used_days' => 0, 'carried_days' => 0, 'encashed_days' => 0],
        );
    }

    private function notifyLeave(Employee $employee, LeaveApplication $application, string $event, string $title, string $body): void
    {
        $organization = Organization::query()->find($employee->organization_id);
        if (! $organization instanceof Organization) {
            return;
        }
        $employee->loadMissing('user', 'manager.user');
        $notifier = app(NotificationService::class);
        $manager = $employee->manager?->user;
        $users = $event === NotificationEvents::LEAVE_APPLIED
            ? ($manager instanceof User ? [$manager] : $notifier->holders($organization, 'leave.approve'))
            : [$employee->user];
        $notifier->publish($organization, $users, $event, $event.':'.$application->id, $title, $body);
    }

    private function restore(LeaveApplication $application): void
    {
        $type = $application->leaveType()->first();
        if ($type === null || ! $type->paid) {
            return;
        }
        $balance = LeaveBalance::query()
            ->where('employee_id', $application->employee_id)
            ->where('leave_type_id', $type->id)
            ->where('year', (int) $application->starts_on->format('Y'))
            ->first();
        if ($balance === null) {
            return;
        }
        $balance->used_days = number_format(max(0, (float) $balance->used_days - (float) $application->days), 2, '.', '');
        $balance->save();
    }
}
