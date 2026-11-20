<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\DepartmentTemplate;
use App\Models\Designation;
use App\Models\DesignationDepartmentMapping;
use App\Models\DesignationTemplate;
use App\Models\Employee;
use App\Models\EmployeeReportingRelationship;
use App\Models\EmploymentType;
use App\Models\JobGrade;
use App\Models\JobLevel;
use App\Models\LegalEntity;
use App\Models\OrganizationDepartment;
use App\Models\OrganizationDesignation;
use App\Services\AuthorizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StructureController extends Controller
{
    public function legalEntities(): JsonResponse
    {
        return ApiResponse::data(LegalEntity::query()->orderBy('legal_name')->get(['id', 'organization_id', 'brand_id', 'legal_name', 'registration_number', 'employee_code_prefix', 'employee_code_padding'])->all());
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

    public function updateEmployeeCode(Request $request, LegalEntity $legalEntity, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $data = $request->validate([
            'employee_code_prefix' => ['required', 'string', 'regex:/^[A-Za-z]{2,8}$/'],
            'employee_code_padding' => ['required', 'integer', 'between:3,8'],
        ]);
        $legalEntity->employee_code_prefix = strtoupper($data['employee_code_prefix']);
        $legalEntity->employee_code_padding = (int) $data['employee_code_padding'];
        $legalEntity->save();
        app(\App\Services\AuditLogger::class)->accessChange($request->user(), app(\App\Services\OrganizationContext::class)->get(), 'employee_code.configured', [
            'legal_entity_id' => $legalEntity->id,
            'prefix' => $legalEntity->employee_code_prefix,
            'padding' => $legalEntity->employee_code_padding,
        ]);

        return ApiResponse::data($legalEntity->only(['id', 'employee_code_prefix', 'employee_code_padding']));
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

    public function departments(Request $request): JsonResponse
    {
        $query = Department::query()->withCount('employees')->orderBy('name');
        if ($request->query('status') === 'archived') {
            $query->where('status', 'archived');
        } elseif ($request->query('include') !== 'all') {
            $query->where('status', 'active');
        }

        return ApiResponse::data($query->get()->map(fn (Department $row) => [
            'id' => $row->id,
            'organization_id' => $row->organization_id,
            'name' => $row->name,
            'branch_id' => $row->branch_id,
            'parent_id' => $row->parent_id,
            'code' => $row->code,
            'status' => $row->status,
            'head_employee_id' => $row->head_employee_id,
            'employees_count' => $row->employees_count,
            'department_template_id' => $row->department_template_id,
            'department_category_id' => $row->department_category_id,
        ])->all());
    }

    public function storeDepartment(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'integer'],
            'head_employee_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'organization_id' => ['prohibited'],
        ]);
        if (Department::query()->where('name', $data['name'])->exists()) {
            return ApiResponse::error('This business already has that department.', 422);
        }
        $department = Department::query()->create([
            ...collect($data)->except('organization_id')->all(),
            'code' => Str::upper(Str::slug($data['name'], '_')),
            'status' => 'active',
        ]);
        OrganizationDepartment::query()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'effective_on' => now()->toDateString(),
        ]);

        return ApiResponse::data($department->only(['id', 'organization_id', 'name', 'status']), 201);
    }

    public function updateDepartment(Request $request, Department $department, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer'],
            'head_employee_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'archived'])],
            'organization_id' => ['prohibited'],
        ]);
        if (isset($data['name']) && Department::query()->where('name', $data['name'])->whereKeyNot($department->id)->exists()) {
            return ApiResponse::error('This business already has that department.', 422);
        }
        if (($data['status'] ?? null) === 'archived') {
            $data['archived_at'] = now();
        }
        if (($data['status'] ?? null) === 'active') {
            $data['archived_at'] = null;
        }
        $department->update(collect($data)->except('organization_id')->all());

        return ApiResponse::data($department->only(['id', 'organization_id', 'name', 'status']));
    }

    public function destroyDepartment(Department $department, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission(request()->user(), 'organization.manage');
        $department->update(['status' => 'archived', 'archived_at' => now()]);
        OrganizationDepartment::query()->where('department_id', $department->id)->update(['status' => 'archived']);

        return ApiResponse::data(['id' => $department->id, 'status' => 'archived']);
    }

    public function departmentTemplates(): JsonResponse
    {
        return ApiResponse::data(DepartmentTemplate::query()->with('category:id,name,slug')->orderBy('name')->get()->map(fn (DepartmentTemplate $row) => [
            'id' => $row->id,
            'name' => $row->name,
            'category' => $row->category?->name,
        ])->all());
    }

    public function adoptDepartments(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $data = $request->validate([
            'template_ids' => ['required', 'array', 'min:1'],
            'template_ids.*' => ['integer'],
        ]);
        $created = [];
        foreach (DepartmentTemplate::query()->whereIn('id', $data['template_ids'])->get() as $template) {
            $department = Department::query()->where('name', $template->name)->first();
            if ($department === null) {
                $department = Department::query()->create([
                    'name' => $template->name,
                    'code' => Str::upper(Str::slug($template->name, '_')),
                    'department_template_id' => $template->id,
                    'department_category_id' => $template->department_category_id,
                    'status' => 'active',
                ]);
                OrganizationDepartment::query()->create([
                    'department_id' => $department->id,
                    'department_template_id' => $template->id,
                    'status' => 'active',
                    'effective_on' => now()->toDateString(),
                ]);
                $created[] = $department->id;
            }
        }

        return ApiResponse::data(['activated_ids' => $created]);
    }

    public function designations(Request $request): JsonResponse
    {
        $query = Designation::query()->orderBy('name');
        if ($request->query('status') === 'archived') {
            $query->where('status', 'archived');
        } elseif ($request->query('include') !== 'all') {
            $query->where('status', 'active');
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }
        if ($request->filled('job_level_id')) {
            $query->where('job_level_id', $request->integer('job_level_id'));
        }

        return ApiResponse::data($query->get()->map(fn (Designation $row) => [
            'id' => $row->id,
            'organization_id' => $row->organization_id,
            'name' => $row->name,
            'code' => $row->code,
            'department_id' => $row->department_id,
            'job_level_id' => $row->job_level_id,
            'job_grade_id' => $row->job_grade_id,
            'status' => $row->status,
            'reports_to_designation_id' => $row->reports_to_designation_id,
        ])->all());
    }

    public function storeDesignation(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $data = $this->designationData($request);
        if (Designation::query()->where('name', $data['name'])->exists()) {
            return ApiResponse::error('This business already has that designation.', 422);
        }
        $this->assertDesignationLinks($data);
        $designation = Designation::query()->create([
            ...$data,
            'code' => $data['code'] ?? Str::upper(Str::slug($data['name'], '_')),
            'status' => 'active',
        ]);
        OrganizationDesignation::query()->create([
            'designation_id' => $designation->id,
            'status' => 'active',
            'effective_on' => now()->toDateString(),
        ]);
        $this->syncMapping($designation);

        return ApiResponse::data($designation->only(['id', 'organization_id', 'name', 'status']), 201);
    }

    public function updateDesignation(Request $request, Designation $designation, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'department_id' => ['nullable', 'integer'],
            'job_level_id' => ['nullable', 'integer'],
            'job_grade_id' => ['nullable', 'integer'],
            'employment_type_id' => ['nullable', 'integer'],
            'reports_to_designation_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'min_experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
            'max_experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
            'responsibilities' => ['nullable', 'string'],
            'skills' => ['nullable', 'string'],
            'salary_grade_code' => ['nullable', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(['active', 'archived'])],
            'organization_id' => ['prohibited'],
        ]);
        unset($data['organization_id']);
        if (isset($data['name']) && Designation::query()->where('name', $data['name'])->whereKeyNot($designation->id)->exists()) {
            return ApiResponse::error('This business already has that designation.', 422);
        }
        $this->assertDesignationLinks($data);
        if (($data['status'] ?? null) === 'archived') {
            $data['archived_at'] = now();
        }
        $designation->update($data);
        $this->syncMapping($designation);

        return ApiResponse::data($designation->only(['id', 'organization_id', 'name', 'status']));
    }

    public function destroyDesignation(Designation $designation, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission(request()->user(), 'organization.manage');
        $designation->update(['status' => 'archived', 'archived_at' => now()]);
        OrganizationDesignation::query()->where('designation_id', $designation->id)->update(['status' => 'archived']);

        return ApiResponse::data(['id' => $designation->id, 'status' => 'archived']);
    }

    public function designationTemplates(): JsonResponse
    {
        return ApiResponse::data(DesignationTemplate::query()->orderBy('category')->orderBy('name')->get(['id', 'category', 'name'])->all());
    }

    public function adoptDesignations(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $data = $request->validate([
            'template_ids' => ['required', 'array', 'min:1'],
            'template_ids.*' => ['integer'],
            'department_id' => ['nullable', 'integer'],
        ]);
        if (isset($data['department_id']) && Department::query()->whereKey($data['department_id'])->where('status', 'active')->doesntExist()) {
            return ApiResponse::error('Choose a department in this business.', 422);
        }
        $created = [];
        foreach (DesignationTemplate::query()->whereIn('id', $data['template_ids'])->get() as $template) {
            $designation = Designation::query()->where('name', $template->name)->first();
            if ($designation === null) {
                $designation = Designation::query()->create([
                    'name' => $template->name,
                    'code' => Str::upper(Str::slug($template->name, '_')),
                    'designation_template_id' => $template->id,
                    'department_id' => $data['department_id'] ?? null,
                    'status' => 'active',
                ]);
                OrganizationDesignation::query()->create([
                    'designation_id' => $designation->id,
                    'designation_template_id' => $template->id,
                    'status' => 'active',
                    'effective_on' => now()->toDateString(),
                ]);
                $this->syncMapping($designation);
                $created[] = $designation->id;
            }
        }

        return ApiResponse::data(['activated_ids' => $created]);
    }

    public function jobLevels(): JsonResponse
    {
        $organizationId = app(\App\Services\OrganizationContext::class)->id();

        return ApiResponse::data(JobLevel::query()->where(function ($query) use ($organizationId): void {
            $query->whereNull('organization_id')->orWhere('organization_id', $organizationId);
        })->orderBy('rank')->get(['id', 'name', 'rank', 'status', 'organization_id'])->all());
    }

    public function jobGrades(): JsonResponse
    {
        $organizationId = app(\App\Services\OrganizationContext::class)->id();

        return ApiResponse::data(JobGrade::query()->where(function ($query) use ($organizationId): void {
            $query->whereNull('organization_id')->orWhere('organization_id', $organizationId);
        })->orderBy('code')->get(['id', 'name', 'code', 'status', 'organization_id'])->all());
    }

    public function employmentTypes(): JsonResponse
    {
        $organizationId = app(\App\Services\OrganizationContext::class)->id();

        return ApiResponse::data(EmploymentType::query()->where(function ($query) use ($organizationId): void {
            $query->whereNull('organization_id')->orWhere('organization_id', $organizationId);
        })->orderBy('name')->get(['id', 'name', 'code', 'status'])->all());
    }

    public function storeReporting(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'manager_employee_id' => ['required', 'integer', 'different:employee_id'],
            'effective_on' => ['required', 'date'],
        ]);
        if (Employee::query()->whereKey($data['employee_id'])->doesntExist() || Employee::query()->whereKey($data['manager_employee_id'])->doesntExist()) {
            return ApiResponse::error('Both people must belong to this business.', 422);
        }
        EmployeeReportingRelationship::query()
            ->where('employee_id', $data['employee_id'])
            ->whereNull('ended_on')
            ->update(['ended_on' => $data['effective_on']]);
        $row = EmployeeReportingRelationship::query()->create($data);
        Employee::query()->whereKey($data['employee_id'])->update(['manager_id' => $data['manager_employee_id']]);

        return ApiResponse::data(['id' => $row->id], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function designationData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:40'],
            'department_id' => ['nullable', 'integer'],
            'job_level_id' => ['nullable', 'integer'],
            'job_grade_id' => ['nullable', 'integer'],
            'employment_type_id' => ['nullable', 'integer'],
            'reports_to_designation_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'min_experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
            'max_experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
            'responsibilities' => ['nullable', 'string'],
            'skills' => ['nullable', 'string'],
            'salary_grade_code' => ['nullable', 'string', 'max:40'],
            'organization_id' => ['prohibited'],
        ]);
        unset($data['organization_id']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertDesignationLinks(array $data): void
    {
        if (isset($data['department_id']) && Department::query()->whereKey($data['department_id'])->doesntExist()) {
            abort(422, 'The department is not in this business.');
        }
        if (isset($data['reports_to_designation_id']) && Designation::query()->whereKey($data['reports_to_designation_id'])->doesntExist()) {
            abort(422, 'The reporting designation is not in this business.');
        }
    }

    private function syncMapping(Designation $designation): void
    {
        if ($designation->department_id === null) {
            return;
        }
        DesignationDepartmentMapping::query()->firstOrCreate([
            'designation_id' => $designation->id,
            'department_id' => $designation->department_id,
        ]);
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
