<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Models\AttendanceRequest;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftRotation;
use App\Models\ShiftRotationAssignment;
use App\Modules\Attendance\Services\AttendanceService;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use App\Support\ApiResponse;
use App\Support\SpreadsheetFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function summary(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'attendance.view');
        $date = $request->validate(['date' => ['nullable', 'date']])['date'] ?? now()->toDateString();
        $ids = $this->visibleIds($request, $authorization);
        $days = AttendanceDay::query()->whereDate('work_date', $date)->whereIn('employee_id', $ids)->get();

        return ApiResponse::data([
            'date' => $date,
            'employees' => count($ids),
            'present' => $days->where('status', 'present')->count(),
            'half_day' => $days->where('status', 'half_day')->count(),
            'absent' => $days->where('status', 'absent')->count(),
            'late' => $days->where('late_minutes', '>', 0)->count(),
            'missing_punch' => $days->where('status', 'missing_punch')->count(),
            'overtime_minutes' => (int) $days->sum('overtime_minutes'),
        ]);
    }

    public function month(Request $request, AuthorizationService $authorization, AttendanceService $attendance): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'attendance.view');
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'year' => ['required', 'integer'], 'month' => ['required', 'integer', 'between:1,12']]);
        $employee = $this->visibleEmployee($request, $authorization, (int) $data['employee_id']);

        return ApiResponse::data($attendance->month($employee, (int) $data['year'], (int) $data['month']));
    }

    public function saveNote(Request $request, AuthorizationService $authorization, AttendanceService $attendance): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'attendance.view');
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'work_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'organization_id' => ['prohibited'],
        ]);
        $employee = $this->visibleEmployee($request, $authorization, (int) $data['employee_id']);

        return ApiResponse::data($attendance->dayPayload($attendance->saveNote($employee, $data['work_date'], $data['note'] ?? null)));
    }

    public function report(Request $request, AuthorizationService $authorization, AttendanceService $attendance): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $authorization->authorizePermission($request->user(), 'attendance.view');
        $data = $request->validate(['year' => ['required', 'integer'], 'month' => ['required', 'integer', 'between:1,12'], 'format' => ['nullable', Rule::in(['json', 'xlsx'])]]);
        $rows = [];
        foreach (Employee::query()->whereIn('id', $this->visibleIds($request, $authorization))->orderBy('employee_code')->get() as $employee) {
            $days = collect($attendance->month($employee, (int) $data['year'], (int) $data['month']));
            $rows[] = [
                $employee->employee_code,
                $employee->fullName(),
                $days->where('status', 'present')->count(),
                $days->where('status', 'half_day')->count(),
                $days->where('status', 'absent')->count(),
                $days->where('late_minutes', '>', 0)->count(),
                $days->where('status', 'missing_punch')->count(),
                (int) $days->sum('overtime_minutes'),
                $days->where('status', 'leave')->count(),
            ];
        }
        if (($data['format'] ?? 'json') === 'xlsx') {
            $binary = SpreadsheetFile::binary(
                ['employee_code', 'name', 'present', 'half_days', 'absent', 'late_days', 'missing_punches', 'overtime_minutes', 'leave_days'],
                $rows,
            );

            return response()->streamDownload(fn () => print $binary, 'attendance.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        return ApiResponse::data($rows);
    }

    public function punch(Request $request, AuthorizationService $authorization, AttendanceService $attendance): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'attendance.view');
        $employee = Employee::query()->where('user_id', $request->user()->id)->first();
        if ($employee === null) {
            return ApiResponse::error('No employee record is linked to this account.', 422);
        }
        $data = $request->validate([
            'kind' => ['required', Rule::in(['in', 'out', 'break_start', 'break_end'])],
            'work_mode' => ['required', Rule::in(['office', 'remote', 'hybrid', 'field'])],
            'work_date' => ['required', 'date'],
            'punched_at' => ['required', 'date'],
            'latitude' => ['prohibited'],
            'longitude' => ['prohibited'],
            'location' => ['prohibited'],
            'organization_id' => ['prohibited'],
        ]);

        return ApiResponse::data($attendance->dayPayload($attendance->punch($employee, $data)), 201);
    }

    public function storeRequest(Request $request, AuthorizationService $authorization, AttendanceService $attendance): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'attendance.view');
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'work_date' => ['required', 'date'],
            'type' => ['required', Rule::in(['correction', 'regularization'])],
            'requested_in' => ['nullable', 'date_format:H:i'],
            'requested_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:500'],
            'organization_id' => ['prohibited'],
        ]);
        $employee = $this->visibleEmployee($request, $authorization, (int) $data['employee_id']);
        if ($employee->user_id !== $request->user()->id && ! $authorization->hasPermission($request->user(), 'attendance.approve', app(\App\Services\OrganizationContext::class)->get())) {
            abort(403, 'You are not allowed to perform this action.');
        }
        $created = $attendance->requestChange($employee, $data);

        return ApiResponse::data($this->requestPayload($created), 201);
    }

    public function queue(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'attendance.approve');
        $rows = AttendanceRequest::query()->with('employee')->where('status', 'pending')->orderBy('id')->get()
            ->filter(function (AttendanceRequest $item) use ($request, $authorization): bool {
                return $authorization->canViewEmployee($request->user(), $item->employee);
            })
            ->map(fn (AttendanceRequest $item) => $this->requestPayload($item))
            ->values();

        return ApiResponse::data($rows->all());
    }

    public function decide(Request $request, AttendanceRequest $attendanceRequest, AuthorizationService $authorization, AttendanceService $attendance): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'attendance.approve');
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        $updated = $attendance->decide($attendanceRequest, $request->user(), (bool) $data['approve']);

        return ApiResponse::data($this->requestPayload($updated));
    }

    public function shifts(): JsonResponse
    {
        return ApiResponse::data(Shift::query()->orderBy('name')->get()->map(fn (Shift $shift) => [
            'id' => $shift->id,
            'name' => $shift->name,
            'starts_at' => $shift->starts_at,
            'ends_at' => $shift->ends_at,
            'grace_minutes' => $shift->grace_minutes,
            'early_grace_minutes' => $shift->early_grace_minutes,
            'overtime_threshold_minutes' => $shift->overtime_threshold_minutes,
            'weekly_offs' => $shift->weekly_offs,
        ])->all());
    }

    public function storeShift(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $this->shiftData($request);
        $shift = Shift::query()->create($data);

        return ApiResponse::data(['id' => $shift->id, 'name' => $shift->name], 201);
    }

    public function updateShift(Request $request, Shift $shift, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $shift->update($this->shiftData($request));

        return ApiResponse::data(['id' => $shift->id]);
    }

    public function destroyShift(Request $request, Shift $shift, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $shift->delete();

        return ApiResponse::data(['deleted' => true]);
    }

    public function assignShift(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'shift_id' => ['required', 'integer'],
            'effective_on' => ['required', 'date'],
            'ended_on' => ['nullable', 'date'],
            'organization_id' => ['prohibited'],
        ]);
        $this->visibleEmployee($request, $authorization, (int) $data['employee_id']);
        if (Shift::query()->whereKey($data['shift_id'])->doesntExist()) {
            return ApiResponse::error('The shift is not in this business.', 422);
        }
        $assignment = ShiftAssignment::query()->create($data);

        return ApiResponse::data(['id' => $assignment->id], 201);
    }

    public function storeRotation(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.shift_id' => ['required', 'integer'],
            'slots.*.day_count' => ['required', 'integer', 'min:1', 'max:60'],
            'organization_id' => ['prohibited'],
        ]);
        $rotation = ShiftRotation::query()->create(['name' => $data['name']]);
        foreach (array_values($data['slots']) as $position => $slot) {
            if (Shift::query()->whereKey($slot['shift_id'])->doesntExist()) {
                return ApiResponse::error('The shift is not in this business.', 422);
            }
            $rotation->slots()->create([
                'position' => $position + 1,
                'shift_id' => $slot['shift_id'],
                'day_count' => $slot['day_count'],
            ]);
        }

        return ApiResponse::data(['id' => $rotation->id], 201);
    }

    public function assignRotation(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'shift_rotation_id' => ['required', 'integer'],
            'starts_on' => ['required', 'date'],
            'ended_on' => ['nullable', 'date'],
            'organization_id' => ['prohibited'],
        ]);
        $this->visibleEmployee($request, $authorization, (int) $data['employee_id']);
        if (ShiftRotation::query()->whereKey($data['shift_rotation_id'])->doesntExist()) {
            return ApiResponse::error('The rotation is not in this business.', 422);
        }

        return ApiResponse::data(['id' => ShiftRotationAssignment::query()->create($data)->id], 201);
    }

    public function calendar(Request $request, AuthorizationService $authorization, AttendanceService $attendance): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'attendance.view');
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'from' => ['required', 'date'], 'to' => ['required', 'date']]);
        $employee = $this->visibleEmployee($request, $authorization, (int) $data['employee_id']);
        $cursor = new \DateTimeImmutable($data['from']);
        $end = new \DateTimeImmutable($data['to']);
        $rows = [];
        while ($cursor <= $end && count($rows) < 62) {
            $date = $cursor->format('Y-m-d');
            $shift = $attendance->resolveShift($employee, $date);
            $rows[] = [
                'date' => $date,
                'shift' => $shift === null ? null : ['id' => $shift->id, 'name' => $shift->name, 'starts_at' => $shift->starts_at, 'ends_at' => $shift->ends_at],
                'weekly_off' => $shift !== null && in_array((int) $cursor->format('N'), array_map('intval', $shift->weekly_offs ?? []), true),
            ];
            $cursor = $cursor->modify('+1 day');
        }

        return ApiResponse::data($rows);
    }

    public function holidays(): JsonResponse
    {
        return ApiResponse::data(Holiday::query()->orderBy('observed_on')->get(['id', 'name', 'observed_on', 'legal_entity_id'])->map(fn (Holiday $holiday) => [
            'id' => $holiday->id,
            'name' => $holiday->name,
            'observed_on' => $holiday->observed_on->toDateString(),
            'legal_entity_id' => $holiday->legal_entity_id,
        ])->all());
    }

    public function storeHoliday(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'observed_on' => ['required', 'date'],
            'legal_entity_id' => ['nullable', 'integer'],
            'organization_id' => ['prohibited'],
        ]);

        return ApiResponse::data(['id' => Holiday::query()->create($data)->id], 201);
    }

    public function showSettings(): JsonResponse
    {
        $setting = AttendanceSetting::query()->first();

        return ApiResponse::data([
            'approval_steps' => $setting->approval_steps ?? ['manager', 'hr'],
            'location_tracking_enabled' => (bool) ($setting->location_tracking_enabled ?? false),
        ]);
    }

    public function updateSettings(Request $request, AuthorizationService $authorization, AttendanceService $attendance): JsonResponse
    {
        $this->manage($request, $authorization);
        $data = $request->validate([
            'approval_steps' => ['required', 'array', 'min:1'],
            'approval_steps.*' => [Rule::in(['manager', 'hr'])],
            'location_tracking_enabled' => ['required', 'boolean'],
            'organization_id' => ['prohibited'],
        ]);
        $setting = $attendance->settings($data['approval_steps'], (bool) $data['location_tracking_enabled']);

        return ApiResponse::data([
            'approval_steps' => $setting->approval_steps,
            'location_tracking_enabled' => $setting->location_tracking_enabled,
        ]);
    }

    private function manage(Request $request, AuthorizationService $authorization): void
    {
        $organization = app(OrganizationContext::class)->get();
        if (! $authorization->hasPermission($request->user(), 'attendance.approve', $organization) && ! $authorization->hasPermission($request->user(), 'organization.manage', $organization)) {
            abort(403, 'You are not allowed to perform this action.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function shiftData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'early_grace_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'overtime_threshold_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'weekly_offs' => ['present', 'array'],
            'weekly_offs.*' => ['integer', 'between:1,7'],
            'organization_id' => ['prohibited'],
        ]);
    }

    /**
     * @return list<int>
     */
    private function visibleIds(Request $request, AuthorizationService $authorization): array
    {
        return $authorization->restrictEmployees(Employee::query(), $request->user())->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function visibleEmployee(Request $request, AuthorizationService $authorization, int $employeeId): Employee
    {
        $employee = Employee::query()->find($employeeId);
        if ($employee === null || ! $authorization->canViewEmployee($request->user(), $employee)) {
            abort(404, 'The requested resource was not found.');
        }

        return $employee;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(AttendanceRequest $request): array
    {
        return [
            'id' => $request->id,
            'employee_id' => $request->employee_id,
            'employee_name' => $request->employee?->fullName(),
            'work_date' => $request->work_date->toDateString(),
            'type' => $request->type,
            'requested_in' => $request->requested_in,
            'requested_out' => $request->requested_out,
            'reason' => $request->reason,
            'status' => $request->status,
            'current_step' => $request->current_step,
        ];
    }
}
