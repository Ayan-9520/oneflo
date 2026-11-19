<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Team;
use App\Services\AuthorizationService;
use App\Support\ApiResponse;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        $teams = Team::query()->with(['leader', 'members.employee', 'department'])->orderBy('name')->get();

        return ApiResponse::data($teams->map(fn (Team $team) => $this->payload($team))->all());
    }

    public function store(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::EMPLOYEES_UPDATE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'department_id' => ['nullable', 'integer'],
            'leader_employee_id' => ['nullable', 'integer'],
            'organization_id' => ['prohibited'],
        ]);
        $this->assertEmployee($data['leader_employee_id'] ?? null);
        $team = Team::query()->create($data);

        return ApiResponse::data($this->payload($team->load(['leader', 'members.employee', 'department'])), 201);
    }

    public function update(Request $request, Team $team, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::EMPLOYEES_UPDATE);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'department_id' => ['nullable', 'integer'],
            'leader_employee_id' => ['nullable', 'integer'],
            'organization_id' => ['prohibited'],
        ]);
        $this->assertEmployee($data['leader_employee_id'] ?? null);
        $team->fill($data);
        $team->save();

        return ApiResponse::data($this->payload($team->load(['leader', 'members.employee', 'department'])));
    }

    public function destroy(Team $team, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission(request()->user(), Permissions::EMPLOYEES_UPDATE);
        $team->delete();

        return ApiResponse::data(['deleted' => true]);
    }

    public function storeMember(Request $request, Team $team, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::EMPLOYEES_UPDATE);
        $data = $request->validate(['employee_id' => ['required', 'integer'], 'organization_id' => ['prohibited']]);
        $this->assertEmployee($data['employee_id']);
        $team->members()->firstOrCreate(['employee_id' => $data['employee_id']]);

        return ApiResponse::data($this->payload($team->load(['leader', 'members.employee', 'department'])), 201);
    }

    public function destroyMember(Team $team, Employee $employee, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission(request()->user(), Permissions::EMPLOYEES_UPDATE);
        $team->members()->where('employee_id', $employee->id)->delete();

        return ApiResponse::data(['deleted' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Team $team): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'department' => $team->department === null ? null : ['id' => $team->department->id, 'name' => $team->department->name],
            'leader' => $team->leader === null ? null : ['id' => $team->leader->id, 'full_name' => $team->leader->fullName()],
            'members' => $team->members->map(fn ($member) => [
                'id' => $member->employee_id,
                'full_name' => $member->employee?->fullName(),
            ])->values(),
        ];
    }

    private function assertEmployee(?int $employeeId): void
    {
        if ($employeeId !== null && Employee::query()->whereKey($employeeId)->doesntExist()) {
            abort(422, 'The employee is not in this business.');
        }
    }
}
