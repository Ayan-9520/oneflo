<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Services;

use App\Models\AttendanceDay;
use App\Models\CompanyProfile;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\LeaveApplication;
use App\Models\LegalEntity;
use App\Models\PayrollAdjustment;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\PayrollSettlement;
use App\Models\StatutoryRule;
use App\Models\User;
use App\Modules\Payroll\PayrollCalculator;
use App\Services\AuditLogger;
use App\Services\OrganizationContext;
use App\Support\SimplePdf;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PayrollService
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public function openRun(LegalEntity $entity, int $year, int $month): PayrollRun
    {
        $exists = PayrollRun::query()
            ->where('legal_entity_id', $entity->id)
            ->where('year', $year)
            ->where('month', $month)
            ->where('status', '!=', 'reversed')
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['month' => ['A payroll run for this legal employer and month is already open.']]);
        }

        try {
            return PayrollRun::query()->create([
                'legal_entity_id' => $entity->id,
                'year' => $year,
                'month' => $month,
                'open_period' => sprintf('%04d-%02d', $year, $month),
                'status' => 'draft',
                'warnings' => [],
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['month' => ['A payroll run for this legal employer and month is already open.']]);
        }
    }

    public function preview(PayrollRun $run, User $actor): PayrollRun
    {
        $this->guardOpen($run);

        return DB::transaction(function () use ($run, $actor): PayrollRun {
            $run->lines()->delete();
            $warnings = [];
            $period = sprintf('%04d-%02d-01', $run->year, $run->month);
            $days = (int) (new DateTimeImmutable($period))->format('t');
            $rules = $this->rules($run->legal_entity_id, $period);
            foreach (['epf', 'esi', 'professional_tax', 'tds'] as $code) {
                if (! isset($rules[$code])) {
                    $warnings[] = strtoupper($code).' was not calculated because no confirmed rule is effective for this month.';
                }
            }
            $employees = Employee::query()->where('legal_entity_id', $run->legal_entity_id)->where('status', 'active')->get();
            foreach ($employees as $employee) {
                $compensation = EmployeeCompensation::query()->with('structure.lines.component')->where('employee_id', $employee->id)->first();
                if ($compensation === null || $compensation->structure === null) {
                    $warnings[] = $employee->employee_code.' has no salary structure.';
                    continue;
                }
                $recorded = AttendanceDay::query()->where('employee_id', $employee->id)->whereYear('work_date', $run->year)->whereMonth('work_date', $run->month)->count();
                if ($recorded < $days) {
                    $warnings[] = $employee->employee_code.' does not have a complete attendance month.';
                    continue;
                }
                $amounts = [];
                $kinds = [];
                $taxable = [];
                foreach ($compensation->structure->lines as $line) {
                    $code = $line->component->code;
                    $amounts[$code] = (int) round(((float) $line->monthly_amount) * 100);
                    $kinds[$code] = $line->component->kind;
                    if ($line->component->taxable) {
                        $taxable[] = $code;
                    }
                }
                $adjustments = [];
                foreach (PayrollAdjustment::query()->where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->get() as $adjustment) {
                    $adjustments[$adjustment->component_code] = ($adjustments[$adjustment->component_code] ?? 0) + $adjustment->amount_paise;
                }
                $lop = AttendanceDay::query()->where('employee_id', $employee->id)->whereYear('work_date', $run->year)->whereMonth('work_date', $run->month)->where('status', 'absent')->count();
                $lop += $this->unpaidLeaveDays($employee, $run->year, $run->month);
                $tdsRule = ($compensation->tax_regime === 'old' ? ($rules['tds_old'] ?? null) : ($rules['tds'] ?? null));
                $result = PayrollCalculator::month($amounts, $kinds, $taxable, $days, $lop, $rules['epf'] ?? null, $rules['esi'] ?? null, $rules['professional_tax'] ?? null, $tdsRule, $adjustments);
                $warnings = [...$warnings, ...array_map(fn (string $warning) => $employee->employee_code.': '.$warning, array_filter($result['warnings'], fn (string $warning) => ! str_contains($warning, 'not calculated')))];
                PayrollLine::query()->create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'lop_days' => $lop,
                    'earnings' => $result['earnings'],
                    'deductions' => $result['deductions'],
                    'employer' => $result['employer'],
                    'gross_paise' => $result['gross'],
                    'deduction_paise' => $result['deduction_total'],
                    'net_paise' => $result['net'],
                ]);
            }
            $run->warnings = array_values(array_unique($warnings));
            $run->status = 'preview';
            $run->save();
            $this->audit->accessChange($actor, $this->context->get(), 'payroll.previewed', ['run_id' => $run->id]);

            return $run->refresh();
        });
    }

    public function submit(PayrollRun $run, User $actor): PayrollRun
    {
        $this->requireStatus($run, 'preview');
        if ($this->blocked($run)) {
            throw ValidationException::withMessages(['status' => ['Resolve the payroll warnings before submitting.']]);
        }
        $run->status = 'submitted';
        $run->submitted_by = $actor->id;
        $run->save();
        $this->audit->accessChange($actor, $this->context->get(), 'payroll.submitted', ['run_id' => $run->id]);

        return $run;
    }

    public function approve(PayrollRun $run, User $actor): PayrollRun
    {
        $this->requireStatus($run, 'submitted');
        if ($run->submitted_by === $actor->id) {
            throw ValidationException::withMessages(['status' => ['The person who submitted payroll cannot approve it.']]);
        }
        $run->status = 'approved';
        $run->approved_by = $actor->id;
        $run->save();
        $this->audit->accessChange($actor, $this->context->get(), 'payroll.approved', ['run_id' => $run->id]);

        return $run;
    }

    public function lock(PayrollRun $run, User $actor): PayrollRun
    {
        $this->requireStatus($run, 'approved');
        $run->status = 'locked';
        $run->locked_by = $actor->id;
        $run->locked_at = now();
        $run->save();
        $this->audit->accessChange($actor, $this->context->get(), 'payroll.locked', ['run_id' => $run->id]);
        $organization = $this->context->get();
        if ($organization !== null) {
            app(\App\Modules\Saas\IntegrationDispatcher::class)->payroll($organization, $run);
            app(\App\Modules\Saas\WorkflowRunner::class)->dispatch($organization, 'payroll.published', 'run:'.$run->id);
        }
        if ($organization !== null) {
            $notifier = app(\App\Modules\Notifications\NotificationService::class);
            $notifier->publish($organization, $notifier->holders($organization, 'payroll.view'), \App\Modules\Notifications\NotificationEvents::PAYROLL_PUBLISHED, 'payroll:'.$run->id, 'Payroll published', 'Payroll for '.$run->year.'-'.str_pad((string) $run->month, 2, '0', STR_PAD_LEFT).' is locked.');
            $run->load('lines.employee.user');
            foreach ($run->lines as $line) {
                $notifier->publish($organization, [$line->employee?->user], \App\Modules\Notifications\NotificationEvents::PAYSLIP_AVAILABLE, 'payslip:'.$line->id, 'Payslip available', 'A payslip is available for '.$run->year.'-'.str_pad((string) $run->month, 2, '0', STR_PAD_LEFT).'.');
            }
        }

        return $run;
    }

    public function reverse(PayrollRun $run, User $actor, string $reason): PayrollRun
    {
        $this->requireStatus($run, 'locked');
        $run->status = 'reversed';
        $run->open_period = null;
        $run->save();
        $this->audit->accessChange($actor, $this->context->get(), 'payroll.reversed', ['run_id' => $run->id, 'reason' => $reason]);

        return $run;
    }

    public function adjust(PayrollRun $run, Employee $employee, string $code, int $paise, string $reason, User $actor): void
    {
        $this->guardOpen($run);
        PayrollAdjustment::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'component_code' => $code,
            'amount_paise' => $paise,
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);
        $this->audit->accessChange($actor, $this->context->get(), 'payroll.adjusted', ['run_id' => $run->id, 'employee_id' => $employee->id, 'component' => $code]);
    }

    public function settle(Employee $employee, string $lastWorkingOn, int $completedYears, float $encashableDays): PayrollSettlement
    {
        $period = $lastWorkingOn;
        $rules = $this->rules((int) $employee->legal_entity_id, $period);
        $compensation = EmployeeCompensation::query()->with('structure.lines.component')->where('employee_id', $employee->id)->first();
        $basic = 0;
        if ($compensation !== null) {
            foreach ($compensation->structure->lines as $line) {
                if ($line->component->code === 'basic') {
                    $basic = (int) round(((float) $line->monthly_amount) * 100);
                }
            }
        }
        $gratuity = isset($rules['gratuity']) ? PayrollCalculator::gratuity($basic, $completedYears, $rules['gratuity']) : 0;
        $encashment = PayrollCalculator::leaveEncashment($basic, $encashableDays, 30);
        $detail = [
            'disclaimer' => 'Full and final figures follow the confirmed rules and salary structure. They are not a statutory determination.',
            'gratuity_rule' => isset($rules['gratuity']),
        ];

        return PayrollSettlement::query()->create([
            'employee_id' => $employee->id,
            'last_working_on' => $lastWorkingOn,
            'gratuity_paise' => $gratuity,
            'leave_encashment_paise' => $encashment,
            'net_paise' => $gratuity + $encashment,
            'detail' => $detail,
        ]);
    }

    /**
     * @return list<string>
     */
    public function payslipLines(PayrollLine $line): array
    {
        $line->load('employee', 'run');
        $profile = CompanyProfile::query()->where('legal_entity_id', $line->run->legal_entity_id)->first();
        $employer = $profile->legal_company_name ?? LegalEntity::query()->find($line->run->legal_entity_id)?->legal_name ?? 'Legal employer';
        $rows = [
            'Payslip',
            $employer,
            $line->employee->fullName().' '.$line->employee->employee_code,
            sprintf('%04d-%02d', $line->run->year, $line->run->month),
            'Gross INR '.number_format($line->gross_paise / 100, 2, '.', ''),
            'Deductions INR '.number_format($line->deduction_paise / 100, 2, '.', ''),
            'Net INR '.number_format($line->net_paise / 100, 2, '.', ''),
            'Calculated from the rules confirmed by the legal employer. This payslip is not a tax or provident-fund certificate.',
        ];

        return $rows;
    }

    public function payslipPdf(PayrollLine $line): string
    {
        if ($line->run->status !== 'locked') {
            throw ValidationException::withMessages(['status' => ['Payslips are available after payroll is locked.']]);
        }

        return SimplePdf::text($this->payslipLines($line));
    }

    /**
     * @return array<string, mixed>
     */
    public function form16(LegalEntity $entity, int $financialYear, ?int $employeeId = null): array
    {
        $profile = CompanyProfile::query()->where('legal_entity_id', $entity->id)->first();
        $from = $financialYear * 100 + 4;
        $until = ($financialYear + 1) * 100 + 3;
        $lines = PayrollLine::query()
            ->when($employeeId !== null, fn ($query) => $query->where('employee_id', $employeeId))
            ->whereHas('run', function ($query) use ($entity, $from, $until): void {
                $query->where('legal_entity_id', $entity->id)->where('status', 'locked')
                    ->whereRaw('(year * 100 + month) between ? and ?', [$from, $until]);
            })
            ->with('employee')
            ->get();
        $byEmployee = [];
        foreach ($lines as $line) {
            $key = $line->employee_id;
            $byEmployee[$key]['name'] = $line->employee->fullName();
            $byEmployee[$key]['gross_paise'] = ($byEmployee[$key]['gross_paise'] ?? 0) + $line->gross_paise;
            $byEmployee[$key]['tds_paise'] = ($byEmployee[$key]['tds_paise'] ?? 0) + (int) ($line->deductions['tds'] ?? 0);
        }

        return [
            'title' => 'Draft salary tax computation',
            'disclaimer' => 'This is not a Form 16 issued through the income-tax portal. OneFlo is not the employer, the deductor, or the tax authority. The legal employer named here must issue the certificate through the authorized process and keep the required verification and signature.',
            'deductor' => [
                'legal_name' => $profile->legal_company_name ?? $entity->legal_name,
                'tan' => $this->mask($profile->tan ?? null),
                'pan' => $this->mask($profile->pan ?? null),
            ],
            'financial_year' => $financialYear.'-'.($financialYear + 1),
            'part_a' => [
                'description' => 'Tax deducted from locked payroll runs. Quarterly deposit details must come from the deductor records, not from OneFlo.',
                'employees' => array_map(fn (array $row) => [
                    'name' => $row['name'],
                    'tds' => number_format($row['tds_paise'] / 100, 2, '.', ''),
                ], array_values($byEmployee)),
            ],
            'part_b' => [
                'description' => 'Salary under the configured components. Exemptions and Chapter VI-A claims are included only when a confirmed rule produced them.',
                'employees' => array_map(fn (array $row) => [
                    'name' => $row['name'],
                    'gross' => number_format($row['gross_paise'] / 100, 2, '.', ''),
                ], array_values($byEmployee)),
            ],
            'form_12ba' => 'No perquisites were calculated. Form 12BA is issued by the employer when perquisites apply.',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rules(int $legalEntityId, string $date): array
    {
        $rows = StatutoryRule::query()
            ->where('legal_entity_id', $legalEntityId)
            ->whereNotNull('confirmed_at')
            ->whereDate('effective_on', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('ended_on')->orWhereDate('ended_on', '>=', $date);
            })
            ->orderByDesc('effective_on')
            ->get();
        $rules = [];
        foreach ($rows as $row) {
            $rules[$row->code] ??= $row->config;
        }

        return $rules;
    }

    private function unpaidLeaveDays(Employee $employee, int $year, int $month): int
    {
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $end = $start->modify('last day of this month');
        $count = 0;
        $applications = LeaveApplication::query()->with('leaveType')->where('employee_id', $employee->id)->where('status', 'approved')->get();
        foreach ($applications as $application) {
            if ($application->leaveType?->paid !== false) {
                continue;
            }
            $cursor = new DateTimeImmutable($application->starts_on->toDateString());
            $until = new DateTimeImmutable($application->ends_on->toDateString());
            while ($cursor <= $until) {
                if ($cursor >= $start && $cursor <= $end) {
                    $count++;
                }
                $cursor = $cursor->modify('+1 day');
            }
        }

        return $count;
    }

    private function blocked(PayrollRun $run): bool
    {
        foreach ($run->warnings ?? [] as $warning) {
            if (str_contains($warning, 'no salary') || str_contains($warning, 'not calculated') || str_contains($warning, 'complete attendance')) {
                return true;
            }
        }

        return $run->lines()->count() === 0;
    }

    private function guardOpen(PayrollRun $run): void
    {
        if (! $run->isOpen()) {
            throw ValidationException::withMessages(['status' => ['This payroll run can no longer be edited.']]);
        }
    }

    private function requireStatus(PayrollRun $run, string $status): void
    {
        if ($run->status !== $status) {
            throw ValidationException::withMessages(['status' => ['The payroll run is not ready for that action.']]);
        }
    }

    private function mask(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return str_repeat('X', max(0, strlen($value) - 4)).substr($value, -4);
    }
}
