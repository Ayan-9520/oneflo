<?php

declare(strict_types=1);

namespace App\Modules\Reports;

use App\Models\Asset;
use App\Models\AssetAllocation;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\ExpenseClaim;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Models\LeaveApplication;
use App\Models\PayrollLine;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Modules\Payroll\Services\PayrollService;
use App\Services\AuthorizationService;
use App\Support\SimplePdf;
use App\Support\SpreadsheetFile;
use Illuminate\Database\Eloquent\Builder;

final class ReportService
{
    /**
     * @var list<string>
     */
    public const REPORTS = [
        'headcount', 'attendance', 'leave', 'payroll', 'department', 'employee-cost',
        'expense', 'asset', 'performance', 'recruitment', 'attrition',
        'employee-attendance', 'employee-leave', 'employee-payslips', 'employee-tax',
        'employee-performance', 'employee-assets',
    ];

    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly PayrollService $payroll,
    ) {}

    /**
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    public function run(string $report, ReportScope $scope, User $actor): array
    {
        $employees = $this->employees($scope, $actor);

        return match ($report) {
            'headcount' => $this->headcount($employees),
            'attendance' => $this->attendance($employees, $scope),
            'leave' => $this->leave($employees, $scope),
            'payroll' => $this->payroll($employees, $scope),
            'department' => $this->department($employees),
            'employee-cost' => $this->cost($employees, $scope),
            'expense' => $this->expenses($employees, $scope),
            'asset' => $this->assets($employees, true),
            'performance' => $this->performance($employees, $scope),
            'recruitment' => $this->recruitment($scope),
            'attrition' => $this->attrition($employees, $scope),
            'employee-attendance' => $this->attendance($employees, $scope),
            'employee-leave' => $this->leave($employees, $scope),
            'employee-payslips' => $this->payroll($employees, $scope),
            'employee-tax' => $this->tax($scope, $actor),
            'employee-performance' => $this->performance($employees, $scope),
            'employee-assets' => $this->assets($employees, false),
            default => abort(404, 'The requested resource was not found.'),
        };
    }

    /**
     * @return array<string, int|string>
     */
    public function dashboard(ReportScope $scope, User $actor): array
    {
        $employees = $this->employees($scope, $actor);
        $ids = (clone $employees)->pluck('id');
        $locked = PayrollLine::query()->whereIn('employee_id', $ids)->whereHas('run', fn ($query) => $query->where('status', 'locked'));

        return [
            'headcount' => (clone $employees)->where('status', 'active')->count(),
            'joiners' => (clone $employees)->when($scope->from, fn ($query) => $query->whereDate('joined_on', '>=', $scope->from))->when($scope->to, fn ($query) => $query->whereDate('joined_on', '<=', $scope->to))->count(),
            'exits' => EmploymentRecord::query()->whereIn('employee_id', $ids)->whereIn('change_type', ['resignation', 'exit'])->count(),
            'present_today' => AttendanceDay::query()->whereIn('employee_id', $ids)->whereDate('work_date', now()->toDateString())->where('status', 'present')->count(),
            'absent_today' => AttendanceDay::query()->whereIn('employee_id', $ids)->whereDate('work_date', now()->toDateString())->where('status', 'absent')->count(),
            'leave_pending' => LeaveApplication::query()->whereIn('employee_id', $ids)->where('status', 'pending')->count(),
            'payroll_gross' => number_format(((int) (clone $locked)->sum('gross_paise')) / 100, 2, '.', ''),
            'openings' => JobOpening::query()->where('status', 'published')->count(),
            'expense_claims' => ExpenseClaim::query()->whereIn('employee_id', $ids)->where('status', 'submitted')->count(),
        ];
    }

    /**
     * @param  array{columns: list<string>, rows: list<list<string>>}  $report
     */
    public function csv(array $report): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $report['columns']);
        foreach ($report['rows'] as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @param  array{columns: list<string>, rows: list<list<string>>}  $report
     */
    public function xlsx(array $report): string
    {
        return SpreadsheetFile::binary($report['columns'], $report['rows']);
    }

    /**
     * @param  array{columns: list<string>, rows: list<list<string>>}  $report
     */
    public function pdf(string $report, array $table): string
    {
        $lines = [$report];
        if ($report === 'employee-tax') {
            $lines[] = 'This is not a Form 16 issued through the income-tax portal. OneFlo is not the employer, the deductor, or the tax authority.';
        }
        $lines[] = implode(' | ', $table['columns']);
        foreach ($table['rows'] as $row) {
            $lines[] = implode(' | ', $row);
        }
        if ($report !== 'employee-tax') {
            $lines[] = 'Powered by OneFlo';
        }

        return SimplePdf::text($lines);
    }

    /**
     * @return Builder<Employee>
     */
    private function employees(ReportScope $scope, User $actor): Builder
    {
        $query = $this->authorization->restrictEmployees(Employee::query(), $actor);
        foreach ([
            'legal_entity_id' => $scope->legalEntityId,
            'department_id' => $scope->departmentId,
            'branch_id' => $scope->branchId,
            'designation_id' => $scope->designationId,
            'id' => $scope->employeeId,
        ] as $column => $value) {
            if ($value !== null) {
                $query->where($column, $value);
            }
        }

        return $query;
    }

    /**
     * @param  Builder<Employee>  $employees
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function headcount(Builder $employees): array
    {
        $rows = $employees->with(['department', 'designation', 'branch', 'legalEntity'])->orderBy('employee_code')->get()->map(fn (Employee $employee) => [
            $employee->employee_code,
            $employee->fullName(),
            $employee->department?->name ?? '',
            $employee->designation?->name ?? '',
            $employee->branch?->name ?? '',
            $employee->legalEntity?->legal_name ?? '',
            $employee->status,
            $employee->joined_on?->toDateString() ?? '',
        ])->all();

        return ['columns' => ['employee_code', 'name', 'department', 'designation', 'branch', 'legal_entity', 'status', 'joined_on'], 'rows' => $rows];
    }

    /**
     * @param  Builder<Employee>  $employees
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function department(Builder $employees): array
    {
        $rows = $employees->with('department')->get()->groupBy(fn (Employee $employee) => $employee->department?->name ?? 'Unassigned')->map(fn ($group, $name) => [$name, (string) $group->count()])->values()->all();

        return ['columns' => ['department', 'headcount'], 'rows' => $rows];
    }

    /**
     * @param  Builder<Employee>  $employees
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function attendance(Builder $employees, ReportScope $scope): array
    {
        $ids = (clone $employees)->pluck('id');
        $rows = AttendanceDay::query()->with('employee')->whereIn('employee_id', $ids)
            ->when($scope->from, fn ($query) => $query->whereDate('work_date', '>=', $scope->from))
            ->when($scope->to, fn ($query) => $query->whereDate('work_date', '<=', $scope->to))
            ->orderBy('work_date')->get()->map(fn (AttendanceDay $day) => [
                $day->employee?->employee_code ?? '', $day->employee?->fullName() ?? '', $day->work_date?->toDateString() ?? '', $day->status, (string) $day->late_minutes,
            ])->all();

        return ['columns' => ['employee_code', 'name', 'work_date', 'status', 'late_minutes'], 'rows' => $rows];
    }

    /**
     * @param  Builder<Employee>  $employees
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function leave(Builder $employees, ReportScope $scope): array
    {
        $ids = (clone $employees)->pluck('id');
        $rows = LeaveApplication::query()->with(['employee', 'leaveType'])->whereIn('employee_id', $ids)
            ->when($scope->from, fn ($query) => $query->whereDate('starts_on', '>=', $scope->from))
            ->when($scope->to, fn ($query) => $query->whereDate('starts_on', '<=', $scope->to))
            ->orderBy('starts_on')->get()->map(fn (LeaveApplication $leave) => [
                $leave->employee?->employee_code ?? '', $leave->employee?->fullName() ?? '', $leave->leaveType?->name ?? '', $leave->starts_on?->toDateString() ?? '', $leave->ends_on?->toDateString() ?? '', (string) $leave->days, $leave->status,
            ])->all();

        return ['columns' => ['employee_code', 'name', 'leave_type', 'starts_on', 'ends_on', 'days', 'status'], 'rows' => $rows];
    }

    /**
     * @param  Builder<Employee>  $employees
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function payroll(Builder $employees, ReportScope $scope): array
    {
        $ids = (clone $employees)->pluck('id');
        $rows = PayrollLine::query()->with(['employee', 'run'])->whereIn('employee_id', $ids)->whereHas('run', function ($query) use ($scope): void {
            $query->where('status', 'locked');
            if ($scope->from) {
                $query->whereRaw('(year * 100 + month) >= ?', [(int) substr($scope->from, 0, 4) * 100 + (int) substr($scope->from, 5, 2)]);
            }
            if ($scope->to) {
                $query->whereRaw('(year * 100 + month) <= ?', [(int) substr($scope->to, 0, 4) * 100 + (int) substr($scope->to, 5, 2)]);
            }
        })->get()->map(fn (PayrollLine $line) => [
            $line->employee?->employee_code ?? '', $line->employee?->fullName() ?? '', (string) $line->run?->year, (string) $line->run?->month,
            number_format($line->gross_paise / 100, 2, '.', ''), number_format($line->deduction_paise / 100, 2, '.', ''), number_format($line->net_paise / 100, 2, '.', ''),
        ])->all();

        return ['columns' => ['employee_code', 'name', 'year', 'month', 'gross', 'deductions', 'net'], 'rows' => $rows];
    }

    /**
     * @param  Builder<Employee>  $employees
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function cost(Builder $employees, ReportScope $scope): array
    {
        $table = $this->payroll($employees, $scope);
        $grouped = [];
        foreach ($table['rows'] as $row) {
            $grouped[$row[0]] = [$row[0], $row[1], number_format((float) ($grouped[$row[0]][2] ?? 0) + (float) $row[4], 2, '.', '')];
        }

        return ['columns' => ['employee_code', 'name', 'gross'], 'rows' => array_values($grouped)];
    }

    /**
     * @param  Builder<Employee>  $employees
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function expenses(Builder $employees, ReportScope $scope): array
    {
        $ids = (clone $employees)->pluck('id');
        $rows = ExpenseClaim::query()->with(['employee', 'category'])->whereIn('employee_id', $ids)
            ->when($scope->from, fn ($query) => $query->whereDate('incurred_on', '>=', $scope->from))
            ->when($scope->to, fn ($query) => $query->whereDate('incurred_on', '<=', $scope->to))
            ->orderByDesc('id')->get()->map(fn (ExpenseClaim $claim) => [
                $claim->employee?->employee_code ?? '', $claim->employee?->fullName() ?? '', $claim->category?->name ?? '', number_format((float) $claim->amount, 2, '.', ''), $claim->incurred_on?->toDateString() ?? '', $claim->status,
            ])->all();

        return ['columns' => ['employee_code', 'name', 'category', 'amount', 'incurred_on', 'status'], 'rows' => $rows];
    }

    /**
     * @param  Builder<Employee>  $employees
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function assets(Builder $employees, bool $includeStock): array
    {
        $ids = (clone $employees)->pluck('id');
        $assigned = AssetAllocation::query()->with(['asset', 'employee'])->whereIn('employee_id', $ids)->whereNull('returned_on')->get();
        $rows = $assigned->map(fn (AssetAllocation $row) => [
            $row->asset?->asset_code ?? '', $row->asset?->name ?? '', $row->asset?->category ?? '', $row->employee?->employee_code ?? '', $row->allocated_on?->toDateString() ?? '', 'assigned',
        ])->all();
        if ($includeStock) {
            foreach (Asset::query()->where('status', 'in_stock')->orderBy('asset_code')->get() as $asset) {
                $rows[] = [$asset->asset_code, $asset->name, $asset->category, '', '', 'in_stock'];
            }
        }

        return ['columns' => ['asset_code', 'name', 'category', 'employee_code', 'allocated_on', 'status'], 'rows' => $rows];
    }

    /**
     * @param  Builder<Employee>  $employees
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function performance(Builder $employees, ReportScope $scope): array
    {
        $ids = (clone $employees)->pluck('id');
        $rows = PerformanceReview::query()->with('employee')->whereIn('employee_id', $ids)
            ->when($scope->from, fn ($query) => $query->whereDate('period_on', '>=', $scope->from))
            ->when($scope->to, fn ($query) => $query->whereDate('period_on', '<=', $scope->to))
            ->orderByDesc('id')->get()->map(fn (PerformanceReview $review) => [
                $review->employee?->employee_code ?? '', $review->employee?->fullName() ?? '', $review->cycle, $review->period_on?->toDateString() ?? '', $review->status, (string) ($review->manager_score ?? ''),
            ])->all();

        return ['columns' => ['employee_code', 'name', 'cycle', 'period_on', 'status', 'manager_score'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function recruitment(ReportScope $scope): array
    {
        $rows = JobOpening::query()
            ->when($scope->from, fn ($query) => $query->whereDate('opened_on', '>=', $scope->from))
            ->when($scope->to, fn ($query) => $query->whereDate('opened_on', '<=', $scope->to))
            ->orderByDesc('id')->get()->map(function (JobOpening $opening): array {
                $count = JobApplication::query()->where('job_opening_id', $opening->id)->count();

                return [$opening->title, $opening->status, (string) $count];
            })->all();

        return ['columns' => ['opening', 'status', 'applications'], 'rows' => $rows];
    }

    /**
     * @param  Builder<Employee>  $employees
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function attrition(Builder $employees, ReportScope $scope): array
    {
        $ids = (clone $employees)->pluck('id');
        $rows = EmploymentRecord::query()->with('employee')->whereIn('employee_id', $ids)->whereIn('change_type', ['resignation', 'exit'])
            ->when($scope->from, fn ($query) => $query->whereDate('effective_on', '>=', $scope->from))
            ->when($scope->to, fn ($query) => $query->whereDate('effective_on', '<=', $scope->to))
            ->orderByDesc('id')->get()->map(fn (EmploymentRecord $record) => [
                $record->employee?->employee_code ?? '', $record->employee?->fullName() ?? '', $record->change_type, $record->effective_on?->toDateString() ?? '', $record->last_working_on?->toDateString() ?? '',
            ])->all();

        return ['columns' => ['employee_code', 'name', 'change_type', 'effective_on', 'last_working_on'], 'rows' => $rows];
    }

    /**
     * @return array{columns: list<string>, rows: list<list<string>>}
     */
    private function tax(ReportScope $scope, User $actor): array
    {
        if ($scope->employeeId === null || $scope->from === null) {
            abort(422, 'A financial year start and an employee are required.');
        }
        $employee = $this->employees($scope, $actor)->first();
        if ($employee === null || $employee->legalEntity === null) {
            abort(404, 'The requested resource was not found.');
        }
        $draft = $this->payroll->form16($employee->legalEntity, (int) substr($scope->from, 0, 4), $employee->id);
        $rows = [];
        foreach ($draft['part_a']['employees'] as $row) {
            $rows[] = [$employee->employee_code, $row['name'], $draft['financial_year'], $row['tds'], $draft['deductor']['legal_name']];
        }

        return ['columns' => ['employee_code', 'name', 'financial_year', 'tds', 'deductor'], 'rows' => $rows];
    }
}
