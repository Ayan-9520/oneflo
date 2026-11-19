<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\Organization;
use App\Modules\Documents\Services\BrandingFileStore;
use App\Modules\Organization\Http\Resources\EmployeeResource;
use App\Modules\Organization\Services\EmployeeLifecycle;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use App\Support\ApiResponse;
use App\Support\Employment;
use App\Support\Permissions;
use App\Support\SpreadsheetFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $query = Employee::query()->with(['brand', 'legalEntity', 'department', 'designation', 'branch', 'manager'])->orderBy('employee_code');
        $authorization->restrictEmployees($query, $request->user());
        foreach (['status', 'department_id', 'designation_id', 'branch_id', 'legal_entity_id', 'brand_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('employee_code', 'like', $term)
                    ->orWhere('given_name', 'like', $term)
                    ->orWhere('family_name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        return ApiResponse::data(EmployeeResource::collection($query->limit(200)->get())->resolve());
    }

    public function show(Employee $employee, AuthorizationService $authorization): EmployeeResource
    {
        $this->visible($employee, $authorization);

        return new EmployeeResource($employee->load(['brand', 'legalEntity', 'department', 'designation', 'branch', 'manager']));
    }

    public function store(Request $request, AuthorizationService $authorization, EmployeeLifecycle $lifecycle): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::EMPLOYEES_CREATE);
        app(\App\Modules\Saas\SaasService::class)->assertEmployeeCapacity();
        $employee = $lifecycle->hire($this->validated($request), $request->user());

        return (new EmployeeResource($employee->load(['brand', 'legalEntity', 'department', 'designation', 'branch', 'manager'])))->response()->setStatusCode(201);
    }

    public function update(Request $request, Employee $employee, AuthorizationService $authorization, EmployeeLifecycle $lifecycle): EmployeeResource
    {
        $authorization->authorizePermission($request->user(), Permissions::EMPLOYEES_UPDATE);
        $this->visible($employee, $authorization);
        $updated = $lifecycle->update($employee, $this->validated($request, partial: true), $request->user());

        return new EmployeeResource($updated->load(['brand', 'legalEntity', 'department', 'designation', 'branch', 'manager']));
    }

    public function destroy(Employee $employee, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission(request()->user(), Permissions::EMPLOYEES_DELETE);
        $this->visible($employee, $authorization);
        if ($employee->employmentRecords()->count() > 1 || $employee->status !== 'active') {
            return ApiResponse::error('Deactivate the employee instead of deleting a record that has employment history.', 422);
        }
        $employee->employmentRecords()->delete();
        $employee->delete();

        return ApiResponse::data(['deleted' => true]);
    }

    public function history(Employee $employee, AuthorizationService $authorization): JsonResponse
    {
        $this->visible($employee, $authorization);
        $user = request()->user();
        $organizationIds = $user->hasPlatformRole()
            ? Organization::query()->pluck('id')
            : $user->organizationAccesses()->pluck('organization_id');
        $records = EmploymentRecord::query()
            ->withoutGlobalScope('organization')
            ->where('person_key', $employee->person_key)
            ->whereIn('organization_id', $organizationIds)
            ->orderBy('effective_on')
            ->orderBy('id')
            ->get(['id', 'organization_id', 'employee_id', 'legal_entity_id', 'department_id', 'designation_id', 'manager_employee_id', 'employment_type', 'status', 'change_type', 'effective_on', 'ended_on', 'last_working_on', 'reason']);

        return ApiResponse::data($records->map(fn (EmploymentRecord $record) => [
            'id' => $record->id,
            'organization_id' => $record->organization_id,
            'employee_id' => $record->employee_id,
            'legal_entity_id' => $record->legal_entity_id,
            'department_id' => $record->department_id,
            'designation_id' => $record->designation_id,
            'manager_employee_id' => $record->manager_employee_id,
            'employment_type' => $record->employment_type,
            'status' => $record->status,
            'change_type' => $record->change_type,
            'effective_on' => $record->effective_on?->toDateString(),
            'ended_on' => $record->ended_on?->toDateString(),
            'last_working_on' => $record->last_working_on?->toDateString(),
            'reason' => $record->reason,
        ])->all());
    }

    public function orgChart(AuthorizationService $authorization): JsonResponse
    {
        $visible = $authorization->restrictEmployees(Employee::query()->where('status', 'active'), request()->user())
            ->with(['designation', 'department'])
            ->orderBy('family_name')
            ->get();

        return ApiResponse::data($visible->map(fn (Employee $employee) => [
            'id' => $employee->id,
            'full_name' => $employee->fullName(),
            'manager_id' => $employee->manager_id,
            'designation' => $employee->designation?->name,
            'department' => $employee->department?->name,
        ])->all());
    }

    public function uploadPhoto(Request $request, Employee $employee, AuthorizationService $authorization, BrandingFileStore $files): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::EMPLOYEES_UPDATE);
        $this->visible($employee, $authorization);
        $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $employee->photo_path = $files->replace($employee->photo_path, $request->file('file'), 'organizations/'.$employee->organization_id.'/employees/'.$employee->id);
        $employee->save();

        return ApiResponse::data(['has_photo' => true]);
    }

    public function photo(Employee $employee, AuthorizationService $authorization, BrandingFileStore $files): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->visible($employee, $authorization);

        return $files->response($employee->photo_path);
    }

    public function export(AuthorizationService $authorization): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $employees = $authorization->restrictEmployees(
            Employee::query()->with(['brand', 'legalEntity', 'department', 'designation', 'branch', 'manager'])->orderBy('employee_code'),
            request()->user(),
        )->get();
        $rows = $employees->map(fn (Employee $employee) => [
            $employee->employee_code,
            $employee->given_name,
            $employee->family_name,
            $employee->email,
            $employee->phone,
            $employee->brand?->name,
            $employee->legalEntity?->legal_name,
            $employee->department?->name,
            $employee->designation?->name,
            $employee->manager?->fullName(),
            $employee->employment_type,
            $employee->joined_on?->toDateString(),
            $employee->status,
            $employee->branch?->name,
            $employee->work_location,
        ])->all();
        $binary = SpreadsheetFile::binary([
            'employee_code', 'given_name', 'family_name', 'email', 'phone', 'brand', 'legal_employer', 'department', 'designation', 'manager', 'employment_type', 'joined_on', 'status', 'branch', 'work_location',
        ], $rows);

        return response()->streamDownload(function () use ($binary): void {
            echo $binary;
        }, 'employees.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function import(Request $request, AuthorizationService $authorization, EmployeeLifecycle $lifecycle): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::EMPLOYEES_CREATE);
        $request->validate(['file' => ['required', 'file', 'max:5120']]);
        $upload = $request->file('file');
        if (! str_ends_with(strtolower($upload->getClientOriginalName()), '.xlsx')) {
            return ApiResponse::error('Import an Excel .xlsx file.', 422);
        }
        $rows = SpreadsheetFile::rows((string) file_get_contents($upload->getRealPath()));
        $created = 0;
        $errors = [];
        foreach ($rows as $index => $row) {
            try {
                $lifecycle->hire([
                    'employee_code' => $row['employee_code'] ?? '',
                    'given_name' => $row['given_name'] ?? '',
                    'family_name' => $row['family_name'] ?? '',
                    'email' => ($row['email'] ?? '') !== '' ? $row['email'] : null,
                    'phone' => ($row['phone'] ?? '') !== '' ? $row['phone'] : null,
                    'employment_type' => ($row['employment_type'] ?? '') !== '' ? $row['employment_type'] : 'permanent',
                    'joined_on' => ($row['joined_on'] ?? '') !== '' ? $row['joined_on'] : null,
                    'work_location' => ($row['work_location'] ?? '') !== '' ? $row['work_location'] : null,
                    'department_id' => $this->idByName(Department::class, $row['department'] ?? ''),
                    'designation_id' => $this->idByName(Designation::class, $row['designation'] ?? ''),
                    'branch_id' => $this->idByName(Branch::class, $row['branch'] ?? ''),
                ], $request->user());
                $created++;
            } catch (ValidationException $exception) {
                $errors[] = ['row' => $index + 2, 'message' => collect($exception->errors())->flatten()->first() ?: $exception->getMessage()];
            } catch (\Throwable $exception) {
                $errors[] = ['row' => $index + 2, 'message' => $exception->getMessage()];
            }
        }

        return ApiResponse::data(['created' => $created, 'errors' => $errors]);
    }

    public function bulk(Request $request, AuthorizationService $authorization, EmployeeLifecycle $lifecycle): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::EMPLOYEES_UPDATE);
        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer'],
            'department_id' => ['nullable', 'integer'],
            'designation_id' => ['nullable', 'integer'],
            'manager_id' => ['nullable', 'integer'],
            'organization_id' => ['prohibited'],
        ]);
        $changes = array_filter([
            'department_id' => $data['department_id'] ?? null,
            'designation_id' => $data['designation_id'] ?? null,
            'manager_id' => $data['manager_id'] ?? null,
        ], fn ($value) => $value !== null);
        if ($changes === []) {
            return ApiResponse::error('Choose a department, designation, or manager to update.', 422);
        }

        return ApiResponse::data(['updated' => $lifecycle->bulk($data['employee_ids'], $changes, $request->user())]);
    }

    public function transfer(Request $request, Employee $employee, AuthorizationService $authorization, EmployeeLifecycle $lifecycle, OrganizationContext $context): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::EMPLOYEES_UPDATE);
        $this->visible($employee, $authorization);
        $data = $request->validate([
            'target_organization_id' => ['required', 'integer'],
            'legal_entity_id' => ['required', 'integer'],
            'employee_code' => ['required', 'string', 'max:40'],
            'brand_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'designation_id' => ['nullable', 'integer'],
            'employment_type' => ['nullable', Rule::in(Employment::TYPES)],
            'work_location' => ['nullable', 'string', 'max:160'],
            'effective_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'organization_id' => ['prohibited'],
        ]);
        $target = Organization::query()->find($data['target_organization_id']);
        if ($target === null || ! $authorization->canEnter($request->user(), $target) || ! $authorization->hasPermission($request->user(), Permissions::EMPLOYEES_CREATE, $target)) {
            abort(403, 'You are not allowed to perform this action.');
        }
        if ($target->id === $context->id()) {
            return ApiResponse::error('Choose a different business for the transfer.', 422);
        }
        $created = $lifecycle->transfer($employee, $target, $data, $request->user());

        return ApiResponse::data(['source_employee_id' => $employee->id, 'employee_id' => $created->id, 'organization_id' => $target->id], 201);
    }

    public function deactivate(Request $request, Employee $employee, AuthorizationService $authorization, EmployeeLifecycle $lifecycle): EmployeeResource
    {
        return $this->statusChange($request, $employee, $authorization, fn () => $lifecycle->deactivate($employee, $request->user(), $request->string('note')->toString() ?: null));
    }

    public function rejoin(Request $request, Employee $employee, AuthorizationService $authorization, EmployeeLifecycle $lifecycle): EmployeeResource
    {
        $request->validate(['effective_on' => ['required', 'date']]);

        return $this->statusChange($request, $employee, $authorization, fn () => $lifecycle->rejoin($employee, $request->user(), $request->string('effective_on')->toString()));
    }

    public function resign(Request $request, Employee $employee, AuthorizationService $authorization, EmployeeLifecycle $lifecycle): EmployeeResource
    {
        $data = $request->validate(['effective_on' => ['required', 'date'], 'last_working_on' => ['nullable', 'date'], 'reason' => ['nullable', 'string', 'max:500']]);

        return $this->statusChange($request, $employee, $authorization, fn () => $lifecycle->resign($employee, $request->user(), $data['effective_on'], $data['last_working_on'] ?? null, $data['reason'] ?? null));
    }

    public function exitEmployment(Request $request, Employee $employee, AuthorizationService $authorization, EmployeeLifecycle $lifecycle): EmployeeResource
    {
        $data = $request->validate(['effective_on' => ['required', 'date'], 'last_working_on' => ['nullable', 'date'], 'reason' => ['nullable', 'string', 'max:500']]);

        return $this->statusChange($request, $employee, $authorization, fn () => $lifecycle->exit($employee, $request->user(), $data['effective_on'], $data['last_working_on'] ?? null, $data['reason'] ?? null));
    }

    private function statusChange(Request $request, Employee $employee, AuthorizationService $authorization, callable $action): EmployeeResource
    {
        $authorization->authorizePermission($request->user(), Permissions::EMPLOYEES_UPDATE);
        $this->visible($employee, $authorization);

        return new EmployeeResource($action()->load(['brand', 'legalEntity', 'department', 'designation', 'branch', 'manager']));
    }

    private function visible(Employee $employee, AuthorizationService $authorization): void
    {
        if (! $authorization->canViewEmployee(request()->user(), $employee)) {
            abort(404, 'The requested resource was not found.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'employee_code' => [$required, 'string', 'max:40'],
            'given_name' => [$required, 'string', 'max:80'],
            'family_name' => [$required, 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['nullable', 'integer'],
            'designation_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'legal_entity_id' => ['nullable', 'integer'],
            'manager_id' => ['nullable', 'integer'],
            'employment_type' => ['nullable', Rule::in(Employment::TYPES)],
            'joined_on' => ['nullable', 'date'],
            'work_location' => ['nullable', 'string', 'max:160'],
            'effective_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'user_id' => ['nullable', 'integer'],
            'organization_id' => ['prohibited'],
        ]);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function idByName(string $model, string $name): ?int
    {
        if ($name === '') {
            return null;
        }
        $id = $model::query()->where('name', $name)->value('id');
        if ($id === null) {
            throw new \RuntimeException('No matching record named '.$name.'.');
        }

        return (int) $id;
    }
}
