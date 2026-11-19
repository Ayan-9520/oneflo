<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use App\Modules\Attendance\Services\LeaveService;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    public function types(): JsonResponse
    {
        return ApiResponse::data(LeaveType::query()->orderBy('name')->get(['id', 'name', 'code', 'paid'])->all());
    }

    public function storeType(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40'],
            'paid' => ['required', 'boolean'],
            'organization_id' => ['prohibited'],
        ]);

        return ApiResponse::data(LeaveType::query()->create($data)->only(['id', 'name', 'code', 'paid']), 201);
    }

    public function policies(): JsonResponse
    {
        return ApiResponse::data(LeavePolicy::query()->with('leaveType')->orderBy('year')->get()->map(fn (LeavePolicy $policy) => [
            'id' => $policy->id,
            'leave_type_id' => $policy->leave_type_id,
            'leave_type' => $policy->leaveType?->name,
            'year' => $policy->year,
            'annual_days' => $policy->annual_days,
            'accrual_days' => $policy->accrual_days,
            'carry_forward_max' => $policy->carry_forward_max,
            'encashment_allowed' => $policy->encashment_allowed,
            'encashment_max' => $policy->encashment_max,
            'approval_steps' => $policy->approval_steps,
        ])->all());
    }

    public function storePolicy(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'leave_type_id' => ['required', 'integer'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'annual_days' => ['required', 'numeric', 'min:0', 'max:365'],
            'accrual_days' => ['required', 'numeric', 'min:0', 'max:31'],
            'carry_forward_max' => ['required', 'numeric', 'min:0', 'max:365'],
            'encashment_allowed' => ['required', 'boolean'],
            'encashment_max' => ['required', 'numeric', 'min:0', 'max:365'],
            'approval_steps' => ['required', 'array', 'min:1'],
            'approval_steps.*' => [Rule::in(['manager', 'hr'])],
            'organization_id' => ['prohibited'],
        ]);
        if (LeaveType::query()->whereKey($data['leave_type_id'])->doesntExist()) {
            return ApiResponse::error('The leave type is not in this business.', 422);
        }

        return ApiResponse::data(['id' => LeavePolicy::query()->create($data)->id], 201);
    }

    public function balances(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'leave.view');
        $year = (int) $request->validate(['year' => ['nullable', 'integer']])['year'] ?? (int) now()->year;
        $ids = $authorization->restrictEmployees(Employee::query(), $request->user())->pluck('id');

        return ApiResponse::data(LeaveBalance::query()->where('year', $year)->whereIn('employee_id', $ids)->get()->map(fn (LeaveBalance $balance) => [
            'employee_id' => $balance->employee_id,
            'leave_type_id' => $balance->leave_type_id,
            'year' => $balance->year,
            'available_days' => $balance->availableDays(),
            'used_days' => $balance->used_days,
            'accrued_days' => $balance->accrued_days,
            'carried_days' => $balance->carried_days,
            'encashed_days' => $balance->encashed_days,
        ])->all());
    }

    public function accrue(Request $request, AuthorizationService $authorization, LeaveService $leave): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate(['year' => ['required', 'integer'], 'month' => ['required', 'integer', 'between:1,12']]);

        return ApiResponse::data(['entries' => $leave->accrue((int) $data['year'], (int) $data['month'])]);
    }

    public function carryForward(Request $request, AuthorizationService $authorization, LeaveService $leave): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate(['from_year' => ['required', 'integer'], 'to_year' => ['required', 'integer', 'gt:from_year']]);

        return ApiResponse::data(['balances' => $leave->carryForward((int) $data['from_year'], (int) $data['to_year'])]);
    }

    public function encash(Request $request, AuthorizationService $authorization, LeaveService $leave): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'leave_type_id' => ['required', 'integer'],
            'year' => ['required', 'integer'],
            'days' => ['required', 'numeric', 'min:0.5'],
            'organization_id' => ['prohibited'],
        ]);
        $employee = Employee::query()->find($data['employee_id']);
        $type = LeaveType::query()->find($data['leave_type_id']);
        if ($employee === null || ! $authorization->canViewEmployee($request->user(), $employee) || $type === null) {
            abort(404, 'The requested resource was not found.');
        }
        $balance = $leave->encash($employee, $type, (int) $data['year'], (float) $data['days']);

        return ApiResponse::data(['available_days' => $balance->availableDays()]);
    }

    public function storeApplication(Request $request, AuthorizationService $authorization, LeaveService $leave): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'leave.view');
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'leave_type_id' => ['required', 'integer'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:500'],
            'organization_id' => ['prohibited'],
        ]);
        $employee = Employee::query()->find($data['employee_id']);
        if ($employee === null || ! $authorization->canViewEmployee($request->user(), $employee)) {
            abort(404, 'The requested resource was not found.');
        }
        if ($employee->user_id !== $request->user()->id && ! $authorization->hasPermission($request->user(), 'leave.approve', app(OrganizationContext::class)->get())) {
            abort(403, 'You are not allowed to perform this action.');
        }
        $application = $leave->apply($employee, $data);

        return ApiResponse::data($this->payload($application), 201);
    }

    public function applications(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'leave.view');
        $ids = $authorization->restrictEmployees(Employee::query(), $request->user())->pluck('id');

        return ApiResponse::data(LeaveApplication::query()->with(['employee', 'leaveType'])->whereIn('employee_id', $ids)->orderByDesc('id')->limit(200)->get()->map(fn (LeaveApplication $application) => $this->payload($application))->all());
    }

    public function queue(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'leave.approve');
        $rows = LeaveApplication::query()->with(['employee', 'leaveType'])->where('status', 'pending')->orderBy('id')->get()
            ->filter(fn (LeaveApplication $application) => $authorization->canViewEmployee($request->user(), $application->employee))
            ->map(fn (LeaveApplication $application) => $this->payload($application))
            ->values();

        return ApiResponse::data($rows->all());
    }

    public function decide(Request $request, LeaveApplication $leaveApplication, AuthorizationService $authorization, LeaveService $leave): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'leave.approve');
        $data = $request->validate(['approve' => ['required', 'boolean']]);

        return ApiResponse::data($this->payload($leave->decide($leaveApplication, $request->user(), (bool) $data['approve'])));
    }

    private function manage(Request $request, AuthorizationService $authorization): void
    {
        $organization = app(OrganizationContext::class)->get();
        if (! $authorization->hasPermission($request->user(), 'leave.approve', $organization) && ! $authorization->hasPermission($request->user(), 'organization.manage', $organization)) {
            abort(403, 'You are not allowed to perform this action.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LeaveApplication $application): array
    {
        return [
            'id' => $application->id,
            'employee_id' => $application->employee_id,
            'employee_name' => $application->employee?->fullName(),
            'leave_type_id' => $application->leave_type_id,
            'leave_type' => $application->leaveType?->name,
            'starts_on' => $application->starts_on->toDateString(),
            'ends_on' => $application->ends_on->toDateString(),
            'days' => $application->days,
            'reason' => $application->reason,
            'status' => $application->status,
            'current_step' => $application->current_step,
        ];
    }
}
