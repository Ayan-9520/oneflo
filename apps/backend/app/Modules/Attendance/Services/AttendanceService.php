<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\AttendanceRequest;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveApplication;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftRotationAssignment;
use App\Models\User;
use App\Modules\Attendance\AttendanceCalculator;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttendanceService
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly OrganizationContext $context,
    ) {}

    /**
     * @param  list<string>  $steps
     */
    public function settings(array $steps, bool $locationTracking): AttendanceSetting
    {
        $clean = array_values(array_filter($steps, fn (string $step) => in_array($step, ['manager', 'hr'], true)));
        if ($clean === []) {
            throw ValidationException::withMessages(['approval_steps' => ['Choose a manager step, an HR step, or both.']]);
        }

        return AttendanceSetting::query()->updateOrCreate([], [
            'approval_steps' => $clean,
            'location_tracking_enabled' => $locationTracking,
        ]);
    }

    /**
     * @return list<string>
     */
    public function approvalSteps(): array
    {
        $steps = AttendanceSetting::query()->value('approval_steps');

        return is_array($steps) && $steps !== [] ? $steps : ['manager', 'hr'];
    }

    public function resolveShift(Employee $employee, string $workDate): ?Shift
    {
        $date = new DateTimeImmutable($workDate);
        $rotation = ShiftRotationAssignment::query()
            ->with('rotation.slots.shift')
            ->where('employee_id', $employee->id)
            ->whereDate('starts_on', '<=', $workDate)
            ->where(function ($query) use ($workDate): void {
                $query->whereNull('ended_on')->orWhereDate('ended_on', '>=', $workDate);
            })
            ->first();
        if ($rotation !== null && $rotation->rotation !== null) {
            $slots = $rotation->rotation->slots;
            $cycle = (int) $slots->sum('day_count');
            if ($cycle > 0) {
                $offset = (int) (new DateTimeImmutable($rotation->starts_on->toDateString()))->diff($date)->format('%a') % $cycle;
                $cursor = 0;
                foreach ($slots as $slot) {
                    $cursor += $slot->day_count;
                    if ($offset < $cursor) {
                        return $slot->shift;
                    }
                }
            }
        }

        $assignment = ShiftAssignment::query()
            ->with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('effective_on', '<=', $workDate)
            ->where(function ($query) use ($workDate): void {
                $query->whereNull('ended_on')->orWhereDate('ended_on', '>=', $workDate);
            })
            ->orderByDesc('effective_on')
            ->first();

        return $assignment?->shift;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function punch(Employee $employee, array $input): AttendanceDay
    {
        $this->rejectCoordinates($input);
        AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'work_date' => $input['work_date'],
            'punched_at' => $input['punched_at'],
            'kind' => $input['kind'],
            'work_mode' => $input['work_mode'],
        ]);

        $day = $this->rebuild($employee, $input['work_date']);
        if (in_array($day->status, ['absent', 'missing_punch'], true) || (int) $day->late_minutes > 0) {
            $organization = \App\Models\Organization::query()->find($employee->organization_id);
            if ($organization instanceof \App\Models\Organization) {
                $employee->loadMissing('user', 'manager.user');
                app(\App\Modules\Notifications\NotificationService::class)->publish(
                    $organization,
                    [$employee->user, $employee->manager?->user],
                    \App\Modules\Notifications\NotificationEvents::ATTENDANCE_EXCEPTION,
                    'attendance:'.$employee->id.':'.$input['work_date'].':'.$day->status,
                    'Attendance exception',
                    'Attendance on '.$input['work_date'].' is '.$day->status.'.',
                );
            }
        }

        return $day;
    }

    public function rebuild(Employee $employee, string $workDate): AttendanceDay
    {
        $shift = $this->resolveShift($employee, $workDate);
        $weekday = (int) (new DateTimeImmutable($workDate))->format('N');
        $weeklyOff = $shift !== null && in_array($weekday, array_map('intval', $shift->weekly_offs ?? []), true);
        $holiday = Holiday::query()
            ->whereDate('observed_on', $workDate)
            ->where(function ($query) use ($employee): void {
                $query->whereNull('legal_entity_id')->orWhere('legal_entity_id', $employee->legal_entity_id);
            })
            ->exists();
        $onLeave = ! $weeklyOff && ! $holiday && LeaveApplication::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', $workDate)
            ->whereDate('ends_on', '>=', $workDate)
            ->exists();
        $punches = AttendancePunch::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate)
            ->orderBy('punched_at')
            ->get();
        $result = AttendanceCalculator::evaluate(
            $workDate,
            $shift?->starts_at,
            $shift?->ends_at,
            (int) ($shift->grace_minutes ?? 0),
            (int) ($shift->early_grace_minutes ?? 0),
            (int) ($shift->overtime_threshold_minutes ?? 0),
            $weeklyOff,
            $holiday,
            $onLeave,
            $punches->map(fn (AttendancePunch $punch) => [
                'kind' => $punch->kind,
                'at' => $punch->punched_at->format('Y-m-d H:i:s'),
            ])->all(),
        );

        $day = AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', $workDate)->first()
            ?? new AttendanceDay(['employee_id' => $employee->id, 'work_date' => $workDate]);
        $day->fill([
            'shift_id' => $shift?->id,
            'work_mode' => $punches->last()?->work_mode,
            ...$result,
        ]);
        $day->save();

        return $day;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function requestChange(Employee $employee, array $input): AttendanceRequest
    {
        $steps = $this->approvalSteps();

        return AttendanceRequest::query()->create([
            'employee_id' => $employee->id,
            'work_date' => $input['work_date'],
            'type' => $input['type'],
            'requested_in' => $input['requested_in'] ?? null,
            'requested_out' => $input['requested_out'] ?? null,
            'reason' => $input['reason'] ?? null,
            'status' => 'pending',
            'current_step' => $steps[0],
            'approval_steps' => $steps,
        ]);
    }

    public function decide(AttendanceRequest $request, User $actor, bool $approve): AttendanceRequest
    {
        if ($request->status !== 'pending' || $request->current_step === null) {
            throw ValidationException::withMessages(['status' => ['This attendance request is already decided.']]);
        }
        $employee = $request->employee()->firstOrFail();
        $this->assertCanDecide($actor, $employee, $request->current_step, 'attendance.approve');
        if (! $approve) {
            $request->status = 'rejected';
            $request->current_step = null;
            $request->save();

            return $request;
        }

        $steps = $request->approval_steps;
        $index = array_search($request->current_step, $steps, true);
        $next = is_int($index) ? ($steps[$index + 1] ?? null) : null;
        if ($next === null) {
            DB::transaction(function () use ($request, $employee): void {
                AttendancePunch::query()->where('employee_id', $employee->id)->whereDate('work_date', $request->work_date)->delete();
                foreach (['in' => $request->requested_in, 'out' => $request->requested_out] as $kind => $time) {
                    if ($time) {
                        AttendancePunch::query()->create([
                            'employee_id' => $employee->id,
                            'work_date' => $request->work_date->toDateString(),
                            'punched_at' => $request->work_date->toDateString().' '.$time,
                            'kind' => $kind,
                            'work_mode' => 'office',
                        ]);
                    }
                }
                $request->status = 'approved';
                $request->current_step = null;
                $request->save();
                $this->rebuild($employee, $request->work_date->toDateString());
            });
        } else {
            $request->current_step = $next;
            $request->save();
        }

        return $request->refresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function month(Employee $employee, int $year, int $month): array
    {
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $days = (int) $start->format('t');
        $rows = [];
        for ($day = 1; $day <= $days; $day++) {
            $date = $start->setDate($year, $month, $day)->format('Y-m-d');
            $rows[] = $this->dayPayload($this->rebuild($employee, $date));
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function dayPayload(AttendanceDay $day): array
    {
        return [
            'id' => $day->id,
            'employee_id' => $day->employee_id,
            'work_date' => $day->work_date->toDateString(),
            'work_mode' => $day->work_mode,
            'status' => $day->status,
            'worked_minutes' => $day->worked_minutes,
            'break_minutes' => $day->break_minutes,
            'scheduled_minutes' => $day->scheduled_minutes,
            'late_minutes' => $day->late_minutes,
            'early_minutes' => $day->early_minutes,
            'overtime_minutes' => $day->overtime_minutes,
            'note' => $day->note,
        ];
    }

    public function saveNote(Employee $employee, string $workDate, ?string $note): AttendanceDay
    {
        $day = $this->rebuild($employee, $workDate);
        $day->note = $note === null || trim($note) === '' ? null : trim($note);
        $day->save();

        return $day;
    }

    public function assertCanDecide(User $actor, Employee $employee, string $step, string $permission): void
    {
        if ($employee->user_id !== null && $employee->user_id === $actor->id) {
            abort(403, 'You are not allowed to perform this action.');
        }
        $employee->loadMissing('manager');
        if ($step === 'manager') {
            $isManager = $employee->manager?->user_id === $actor->id;
            $coversOpenTeam = $employee->manager_id === null
                && $this->allows($actor, $permission)
                && $this->authorization->canViewEmployee($actor, $employee);
            if ($isManager || $coversOpenTeam) {
                return;
            }
        }
        if ($step === 'hr' && $this->allows($actor, 'employees.view') && $this->allows($actor, $permission)) {
            return;
        }

        abort(403, 'You are not allowed to perform this action.');
    }

    private function allows(User $actor, string $permission): bool
    {
        return $this->authorization->hasPermission($actor, $permission, $this->context->get());
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function rejectCoordinates(array $input): void
    {
        if (! array_key_exists('latitude', $input) && ! array_key_exists('longitude', $input) && ! array_key_exists('location', $input)) {
            return;
        }
        $enabled = (bool) AttendanceSetting::query()->value('location_tracking_enabled');
        if (! $enabled || empty($input['location_consent'])) {
            throw ValidationException::withMessages([
                'location' => ['Location capture stays off unless this business enables it and the employee gives consent. Coordinates are not stored.'],
            ]);
        }
    }
}
