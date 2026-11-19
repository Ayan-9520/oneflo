<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AssetAllocation;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeFile;
use App\Models\ExpenseClaim;
use App\Models\IssuedDocument;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\PayrollLine;
use App\Models\PerformanceReview;
use App\Models\Post;
use App\Models\Ticket;
use App\Modules\Payroll\Services\PayrollService;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use App\Support\ApiResponse;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function me(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->self($request, $authorization);
        $employee = $this->own($request);
        if ($employee === null) {
            return ApiResponse::error('No employee record is linked to this account.', 422);
        }
        $employee->load('manager', 'department', 'designation');
        $today = AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('work_date', now()->toDateString())->first();

        return ApiResponse::data([
            'profile' => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->fullName(),
                'email' => $employee->email,
                'phone' => $employee->phone,
                'department' => $employee->department?->name,
                'designation' => $employee->designation?->name,
                'manager' => $employee->manager === null ? null : [
                    'id' => $employee->manager->id,
                    'full_name' => $employee->manager->fullName(),
                    'email' => $employee->manager->email,
                ],
            ],
            'attendance_today' => $today === null ? null : [
                'status' => $today->status,
                'late_minutes' => $today->late_minutes,
                'work_mode' => $today->work_mode,
            ],
            'attendance_history' => AttendanceDay::query()->where('employee_id', $employee->id)->orderByDesc('work_date')->limit(14)->get()->map(fn (AttendanceDay $day) => [
                'work_date' => $day->work_date?->toDateString(),
                'status' => $day->status,
            ])->all(),
            'leave_balances' => LeaveBalance::query()->where('employee_id', $employee->id)->where('year', (int) now()->year)->get()->map(fn (LeaveBalance $balance) => [
                'leave_type_id' => $balance->leave_type_id,
                'available_days' => $balance->availableDays(),
            ])->all(),
            'announcements' => Post::query()->where('kind', 'announcement')->orderByDesc('id')->limit(5)->get(['id', 'title', 'body'])->all(),
            'documents' => [
                'files' => EmployeeFile::query()->where('employee_id', $employee->id)->where('sensitive', false)->orderByDesc('id')->get(['id', 'label', 'category'])->all(),
                'issued' => IssuedDocument::query()->where('employee_id', $employee->id)->orderByDesc('id')->get(['id', 'document_type', 'number', 'issued_on'])->all(),
            ],
            'assets' => AssetAllocation::query()->with('asset')->where('employee_id', $employee->id)->orderByDesc('id')->get()->map(fn (AssetAllocation $row) => [
                'asset_code' => $row->asset?->asset_code,
                'name' => $row->asset?->name,
                'allocated_on' => $row->allocated_on?->toDateString(),
                'returned_on' => $row->returned_on?->toDateString(),
            ])->all(),
            'expenses' => ExpenseClaim::query()->with('category')->where('employee_id', $employee->id)->orderByDesc('id')->limit(10)->get()->map(fn (ExpenseClaim $claim) => [
                'id' => $claim->id,
                'category' => $claim->category?->name,
                'amount' => number_format((float) $claim->amount, 2, '.', ''),
                'status' => $claim->status,
            ])->all(),
            'performance' => PerformanceReview::query()->where('employee_id', $employee->id)->orderByDesc('id')->get()->map(fn (PerformanceReview $review) => [
                'id' => $review->id,
                'cycle' => $review->cycle,
                'status' => $review->status,
                'self_score' => $review->self_score,
                'manager_score' => $review->manager_score,
            ])->all(),
            'tickets' => Ticket::query()->where('employee_id', $employee->id)->orderByDesc('id')->limit(10)->get(['id', 'subject', 'status', 'priority'])->all(),
            'payslips' => PayrollLine::query()->with('run')->where('employee_id', $employee->id)->whereHas('run', fn ($query) => $query->where('status', 'locked'))->orderByDesc('id')->get()->map(fn (PayrollLine $line) => [
                'id' => $line->id,
                'year' => $line->run?->year,
                'month' => $line->run?->month,
                'net' => number_format($line->net_paise / 100, 2, '.', ''),
            ])->all(),
        ]);
    }

    public function tax(Request $request, AuthorizationService $authorization, PayrollService $payroll): JsonResponse
    {
        $this->self($request, $authorization);
        $employee = $this->own($request);
        if ($employee === null || $employee->legal_entity_id === null) {
            return ApiResponse::error('No employee record is linked to this account.', 422);
        }
        $year = (int) ($request->validate(['financial_year' => ['required', 'integer']])['financial_year']);
        $entity = $employee->legalEntity;
        if ($entity === null) {
            abort(404, 'The requested resource was not found.');
        }

        return ApiResponse::data($payroll->form16($entity, $year, $employee->id));
    }

    public function team(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->manager($request, $authorization);
        $ids = $this->teamIds($request, $authorization);
        $today = now()->toDateString();

        $attendance = AttendanceDay::query()
            ->whereIn('employee_id', $ids === [] ? [-1] : $ids)
            ->whereDate('work_date', $today)
            ->get()
            ->keyBy('employee_id');

        return ApiResponse::data([
            'employees' => Employee::query()->with('department')->whereIn('id', $ids === [] ? [-1] : $ids)->orderBy('employee_code')->get()->map(function (Employee $employee) use ($attendance): array {
                return [
                    'id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'full_name' => $employee->fullName(),
                    'department' => $employee->department?->name,
                    'attendance' => $attendance->get($employee->id)?->status,
                ];
            })->all(),
            'leave_queue' => LeaveApplication::query()->with(['employee', 'leaveType'])->whereIn('employee_id', $ids)->where('status', 'pending')->orderBy('id')->get()->map(fn (LeaveApplication $application) => [
                'id' => $application->id,
                'employee' => $application->employee?->fullName(),
                'leave_type' => $application->leaveType?->name,
                'starts_on' => $application->starts_on?->toDateString(),
                'ends_on' => $application->ends_on?->toDateString(),
            ])->all(),
            'performance' => PerformanceReview::query()->with('employee')->whereIn('employee_id', $ids)->orderByDesc('id')->limit(20)->get()->map(fn (PerformanceReview $review) => [
                'employee' => $review->employee?->fullName(),
                'cycle' => $review->cycle,
                'status' => $review->status,
                'manager_score' => $review->manager_score,
            ])->all(),
            'expenses' => ExpenseClaim::query()->with('employee')->whereIn('employee_id', $ids)->whereIn('status', ['submitted', 'manager_approved'])->orderByDesc('id')->get()->map(fn (ExpenseClaim $claim) => [
                'id' => $claim->id,
                'employee' => $claim->employee?->fullName(),
                'amount' => number_format((float) $claim->amount, 2, '.', ''),
                'status' => $claim->status,
            ])->all(),
        ]);
    }

    public function employee(Request $request, Employee $employee, AuthorizationService $authorization): JsonResponse
    {
        $this->manager($request, $authorization);
        if (! in_array($employee->id, $this->teamIds($request, $authorization), true)) {
            abort(404, 'The requested resource was not found.');
        }
        $employee->load('department', 'designation', 'manager');

        return ApiResponse::data([
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'full_name' => $employee->fullName(),
            'email' => $employee->email,
            'department' => $employee->department?->name,
            'designation' => $employee->designation?->name,
            'manager' => $employee->manager?->fullName(),
        ]);
    }

    private function self(Request $request, AuthorizationService $authorization): void
    {
        $organization = app(OrganizationContext::class)->get();
        $user = $request->user();
        foreach ([Permissions::EMPLOYEES_VIEW_OWN, Permissions::EMPLOYEES_VIEW, Permissions::EMPLOYEES_VIEW_TEAM] as $permission) {
            if ($authorization->hasPermission($user, $permission, $organization)) {
                return;
            }
        }
        abort(403, 'You are not allowed to perform this action.');
    }

    private function manager(Request $request, AuthorizationService $authorization): void
    {
        $organization = app(OrganizationContext::class)->get();
        $user = $request->user();
        foreach ([Permissions::EMPLOYEES_VIEW, Permissions::EMPLOYEES_VIEW_TEAM, Permissions::LEAVE_APPROVE] as $permission) {
            if ($authorization->hasPermission($user, $permission, $organization)) {
                return;
            }
        }
        abort(403, 'You are not allowed to perform this action.');
    }

    /**
     * @return list<int>
     */
    private function teamIds(Request $request, AuthorizationService $authorization): array
    {
        return $authorization->restrictEmployees(Employee::query(), $request->user())
            ->where(function ($query) use ($request): void {
                $query->whereNull('user_id')->orWhere('user_id', '!=', $request->user()->id);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function own(Request $request): ?Employee
    {
        return Employee::query()->with('legalEntity')->where('user_id', $request->user()->id)->first();
    }
}
