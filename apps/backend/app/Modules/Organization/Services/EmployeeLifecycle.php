<?php

declare(strict_types=1);

namespace App\Modules\Organization\Services;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Support\Employment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EmployeeLifecycle
{
    public function __construct(private readonly OrganizationContext $context) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function hire(array $data, ?User $actor): Employee
    {
        return DB::transaction(function () use ($data, $actor): Employee {
            $this->assertReferences($data, null);
            if (Employee::query()->where('employee_code', $data['employee_code'])->exists()) {
                throw ValidationException::withMessages(['employee_code' => ['This employee ID is already used in this business.']]);
            }
            $employee = Employee::query()->create([
                ...$this->snapshot($data),
                'person_key' => $data['person_key'] ?? null,
                'employee_code' => $data['employee_code'],
                'given_name' => $data['given_name'],
                'family_name' => $data['family_name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'status' => 'active',
                'joined_on' => $data['joined_on'] ?? now()->toDateString(),
            ]);
            $this->openRecord($employee, 'hire', $actor, $data['joined_on'] ?? null, $data['note'] ?? null);
            $organization = $this->context->get();
            if ($organization !== null) {
                $notifier = app(\App\Modules\Notifications\NotificationService::class);
                $notifier->publish($organization, $notifier->holders($organization, 'employees.view'), \App\Modules\Notifications\NotificationEvents::EMPLOYEE_JOINED, 'employee:'.$employee->id, 'New employee joining', $employee->fullName().' joined on '.$employee->joined_on?->toDateString().'.');
            }

            return $employee->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data, ?User $actor): Employee
    {
        return DB::transaction(function () use ($employee, $data, $actor): Employee {
            $this->assertReferences($data, $employee);
            $personal = array_intersect_key($data, array_flip(['given_name', 'family_name', 'email', 'phone', 'user_id']));
            $snapshot = $this->snapshot($data, $employee);
            $changed = false;
            foreach ($snapshot as $field => $value) {
                if ((string) $employee->getAttribute($field) !== (string) $value && ! ($employee->getAttribute($field) === null && $value === null)) {
                    $changed = true;
                }
            }
            $employee->fill([...$personal, ...$snapshot]);
            $change = $this->changeType($data, $employee);
            $employee->save();
            if ($changed) {
                $this->openRecord($employee, $change, $actor, $data['effective_on'] ?? null, $data['note'] ?? null);
            }

            return $employee->refresh();
        });
    }

    /**
     * @param  list<int>  $employeeIds
     * @param  array<string, mixed>  $changes
     */
    public function bulk(array $employeeIds, array $changes, ?User $actor): int
    {
        $count = 0;
        foreach (Employee::query()->whereIn('id', $employeeIds)->get() as $employee) {
            $this->update($employee, [...$changes, 'note' => 'Bulk update'], $actor);
            $latest = $employee->employmentRecords()->latest('id')->first();
            if ($latest !== null && $latest->change_type !== 'hire') {
                $latest->change_type = 'bulk_update';
                $latest->save();
            }
            $count++;
        }

        return $count;
    }

    public function deactivate(Employee $employee, ?User $actor, ?string $note): Employee
    {
        return $this->setStatus($employee, 'inactive', 'deactivation', $actor, $note, null, null);
    }

    public function rejoin(Employee $employee, ?User $actor, string $effectiveOn): Employee
    {
        if (! in_array($employee->status, ['inactive', 'resigned', 'exited'], true)) {
            throw ValidationException::withMessages(['status' => ['Only an inactive, resigned, or exited employee can rejoin.']]);
        }

        return $this->setStatus($employee, 'active', 'rejoining', $actor, null, $effectiveOn, null);
    }

    public function resign(Employee $employee, ?User $actor, string $effectiveOn, ?string $lastWorkingOn, ?string $reason): Employee
    {
        return $this->setStatus($employee, 'resigned', 'resignation', $actor, $reason, $effectiveOn, $lastWorkingOn);
    }

    public function exit(Employee $employee, ?User $actor, string $effectiveOn, ?string $lastWorkingOn, ?string $reason): Employee
    {
        return $this->setStatus($employee, 'exited', 'exit', $actor, $reason, $effectiveOn, $lastWorkingOn);
    }

    /**
     * @param  array<string, mixed>  $placement
     */
    public function transfer(Employee $employee, Organization $target, array $placement, User $actor): Employee
    {
        if ($employee->status === 'transferred') {
            throw ValidationException::withMessages(['employee' => ['This employee has already been transferred.']]);
        }

        return DB::transaction(function () use ($employee, $target, $placement, $actor): Employee {
            $source = $this->context->get();
            try {
                $this->setStatus($employee, 'transferred', 'transfer', $actor, $placement['note'] ?? null, $placement['effective_on'] ?? null, null);
                $employee->user_id = null;
                $employee->save();

                $this->context->set($target);
                $created = $this->hire([
                    'person_key' => $employee->person_key,
                    'employee_code' => $placement['employee_code'],
                    'given_name' => $employee->given_name,
                    'family_name' => $employee->family_name,
                    'email' => $employee->email,
                    'phone' => $employee->phone,
                    'legal_entity_id' => $placement['legal_entity_id'],
                    'brand_id' => $placement['brand_id'] ?? null,
                    'branch_id' => $placement['branch_id'] ?? null,
                    'department_id' => $placement['department_id'] ?? null,
                    'designation_id' => $placement['designation_id'] ?? null,
                    'employment_type' => $placement['employment_type'] ?? $employee->employment_type,
                    'work_location' => $placement['work_location'] ?? $employee->work_location,
                    'joined_on' => $placement['effective_on'] ?? now()->toDateString(),
                    'note' => 'Transfer',
                ], $actor);
                $created->employmentRecords()->latest('id')->first()?->forceFill(['change_type' => 'transfer'])->save();

                return $created;
            } finally {
                if ($source !== null) {
                    $this->context->set($source);
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertReferences(array $data, ?Employee $employee): void
    {
        foreach (['department_id' => Department::class, 'designation_id' => Designation::class, 'branch_id' => Branch::class, 'brand_id' => Brand::class, 'legal_entity_id' => LegalEntity::class] as $field => $class) {
            if (! empty($data[$field]) && $class::query()->whereKey($data[$field])->doesntExist()) {
                throw ValidationException::withMessages([$field => ['The selected record is not in this business.']]);
            }
        }

        if (! empty($data['manager_id'])) {
            $managerId = (int) $data['manager_id'];
            if ($employee !== null && $managerId === $employee->id) {
                throw ValidationException::withMessages(['manager_id' => ['An employee cannot report to themselves.']]);
            }
            if (Employee::query()->whereKey($managerId)->doesntExist()) {
                throw ValidationException::withMessages(['manager_id' => ['The manager is not in this business.']]);
            }
            $cursor = $managerId;
            $guard = 0;
            while ($cursor !== null && $guard < 50) {
                if ($employee !== null && $cursor === $employee->id) {
                    throw ValidationException::withMessages(['manager_id' => ['That manager would create a reporting cycle.']]);
                }
                $cursor = Employee::query()->whereKey($cursor)->value('manager_id');
                $guard++;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function snapshot(array $data, ?Employee $employee = null): array
    {
        $current = [
            'department_id' => $employee?->department_id,
            'designation_id' => $employee?->designation_id,
            'branch_id' => $employee?->branch_id,
            'brand_id' => $employee?->brand_id,
            'legal_entity_id' => $employee?->legal_entity_id,
            'manager_id' => $employee?->manager_id,
            'employment_type' => $employee?->employment_type ?? 'permanent',
            'work_location' => $employee?->work_location,
        ];

        foreach ($current as $field => $value) {
            if (array_key_exists($field, $data)) {
                $current[$field] = $data[$field] === '' ? null : $data[$field];
            }
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function changeType(array $data, Employee $before): string
    {
        $map = [
            'designation_id' => 'promotion',
            'department_id' => 'department_change',
            'manager_id' => 'manager_change',
        ];
        $matched = null;
        foreach ($map as $field => $type) {
            if (array_key_exists($field, $data) && (int) $data[$field] !== (int) $before->getOriginal($field)) {
                if ($matched !== null) {
                    return 'update';
                }
                $matched = $type;
            }
        }

        return $matched ?? 'update';
    }

    private function setStatus(Employee $employee, string $status, string $change, ?User $actor, ?string $note, ?string $effectiveOn, ?string $lastWorkingOn): Employee
    {
        return DB::transaction(function () use ($employee, $status, $change, $actor, $note, $effectiveOn, $lastWorkingOn): Employee {
            $employee->status = $status;
            $employee->save();
            $record = $this->openRecord($employee, $change, $actor, $effectiveOn, $note);
            if ($lastWorkingOn !== null) {
                $record->last_working_on = $lastWorkingOn;
                $record->save();
            }

            return $employee->refresh();
        });
    }

    private function openRecord(Employee $employee, string $change, ?User $actor, ?string $effectiveOn, ?string $note): EmploymentRecord
    {
        $effective = $effectiveOn ?? now()->toDateString();
        $employee->employmentRecords()->whereNull('ended_on')->update(['ended_on' => $effective]);

        return $employee->employmentRecords()->create([
            'person_key' => $employee->person_key,
            'legal_entity_id' => $employee->legal_entity_id,
            'brand_id' => $employee->brand_id,
            'branch_id' => $employee->branch_id,
            'department_id' => $employee->department_id,
            'designation_id' => $employee->designation_id,
            'manager_employee_id' => $employee->manager_id,
            'employment_type' => $employee->employment_type ?? 'permanent',
            'work_location' => $employee->work_location,
            'status' => $employee->status,
            'change_type' => $change,
            'effective_on' => $effective,
            'reason' => $note,
            'created_by' => $actor?->id,
        ]);
    }
}
