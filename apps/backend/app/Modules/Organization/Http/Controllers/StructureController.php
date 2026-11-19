<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\LegalEntity;
use App\Services\AuthorizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StructureController extends Controller
{
    public function legalEntities(): JsonResponse
    {
        return ApiResponse::data(LegalEntity::query()->orderBy('legal_name')->get(['id', 'organization_id', 'brand_id', 'legal_name', 'registration_number'])->all());
    }

    public function storeLegalEntity(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:160'],
            'registration_number' => ['nullable', 'string', 'max:80'],
            'brand_id' => ['nullable', 'integer'],
            'organization_id' => ['prohibited'],
        ]);

        $entity = LegalEntity::query()->create(collect($data)->except('organization_id')->all());

        return ApiResponse::data($entity->only(['id', 'organization_id', 'legal_name']), 201);
    }

    public function branches(): JsonResponse
    {
        return ApiResponse::data(Branch::query()->orderBy('name')->get(['id', 'organization_id', 'name', 'code'])->all());
    }

    public function storeBranch(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40'],
            'business_unit_id' => ['nullable', 'integer'],
            'organization_id' => ['prohibited'],
        ]);

        $branch = Branch::query()->create(collect($data)->except('organization_id')->all());

        return ApiResponse::data($branch->only(['id', 'organization_id', 'name', 'code']), 201);
    }

    public function departments(): JsonResponse
    {
        return ApiResponse::data(Department::query()->orderBy('name')->get(['id', 'organization_id', 'name', 'branch_id'])->all());
    }

    public function storeDepartment(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer'],
            'organization_id' => ['prohibited'],
        ]);

        $department = Department::query()->create(collect($data)->except('organization_id')->all());

        return ApiResponse::data($department->only(['id', 'organization_id', 'name']), 201);
    }

    public function updateDepartment(Request $request, Department $department, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $department->update($request->validate(['name' => ['required', 'string', 'max:120'], 'organization_id' => ['prohibited']]));

        return ApiResponse::data($department->only(['id', 'organization_id', 'name']));
    }

    public function destroyDepartment(Department $department, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission(request()->user(), 'organization.manage');
        if (Employee::query()->where('department_id', $department->id)->exists()) {
            return ApiResponse::error('Move employees out of this department before deleting it.', 422);
        }
        $department->delete();

        return ApiResponse::data(['deleted' => true]);
    }

    public function designations(): JsonResponse
    {
        return ApiResponse::data(Designation::query()->orderBy('name')->get(['id', 'organization_id', 'name'])->all());
    }

    public function storeDesignation(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'organization_id' => ['prohibited'],
        ]);

        $designation = Designation::query()->create(['name' => $data['name']]);

        return ApiResponse::data($designation->only(['id', 'organization_id', 'name']), 201);
    }

    public function updateDesignation(Request $request, Designation $designation, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $designation->update($request->validate(['name' => ['required', 'string', 'max:120'], 'organization_id' => ['prohibited']]));

        return ApiResponse::data($designation->only(['id', 'organization_id', 'name']));
    }

    public function destroyDesignation(Designation $designation, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission(request()->user(), 'organization.manage');
        if (Employee::query()->where('designation_id', $designation->id)->exists()) {
            return ApiResponse::error('Move employees off this designation before deleting it.', 422);
        }
        $designation->delete();

        return ApiResponse::data(['deleted' => true]);
    }

    public function updateBranch(Request $request, Branch $branch, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $branch->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:40'],
            'organization_id' => ['prohibited'],
        ]));

        return ApiResponse::data($branch->only(['id', 'organization_id', 'name', 'code']));
    }

    public function destroyBranch(Branch $branch, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission(request()->user(), 'organization.manage');
        if (Employee::query()->where('branch_id', $branch->id)->exists()) {
            return ApiResponse::error('Move employees out of this branch before deleting it.', 422);
        }
        $branch->delete();

        return ApiResponse::data(['deleted' => true]);
    }

    public function businessUnits(): JsonResponse
    {
        return ApiResponse::data(BusinessUnit::query()->orderBy('name')->get(['id', 'organization_id', 'name', 'legal_entity_id'])->all());
    }

    public function storeBusinessUnit(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'legal_entity_id' => ['nullable', 'integer'],
            'organization_id' => ['prohibited'],
        ]);

        $unit = BusinessUnit::query()->create(collect($data)->except('organization_id')->all());

        return ApiResponse::data($unit->only(['id', 'organization_id', 'name']), 201);
    }
}
