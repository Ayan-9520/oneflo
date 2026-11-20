<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DepartmentTemplate;
use App\Models\Designation;
use App\Models\DesignationDepartmentMapping;
use App\Models\DesignationTemplate;
use App\Models\Organization;
use App\Models\OrganizationDepartment;
use App\Models\OrganizationDesignation;
use App\Services\OrganizationContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Activates each business's departments and designations from the workforce master templates.
 * Existing records are never renamed, reactivated, or removed; archived departments are skipped.
 */
class BusinessRolesSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{common: array<string, list<string>>, businesses: array<string, array<string, list<string>>>} $map */
        $map = require __DIR__.'/data/business_roles.php';
        $context = app(OrganizationContext::class);

        foreach ($map['businesses'] as $slug => $specific) {
            $organization = Organization::query()->where('slug', $slug)->first();
            if ($organization === null) {
                continue;
            }
            $context->set($organization);
            foreach ([...$map['common'], ...$specific] as $departmentName => $designations) {
                $department = $this->department($departmentName);
                if ($department === null) {
                    continue;
                }
                foreach ($designations as $designationName) {
                    $this->designation($designationName, $department);
                }
            }
        }
        $context->forget();
    }

    private function department(string $name): ?Department
    {
        $existing = Department::query()->where('name', $name)->orderByRaw("status = 'active' desc")->first();
        if ($existing !== null) {
            return $existing->status === 'active' ? $existing : null;
        }
        $template = DepartmentTemplate::query()->where('name', $name)->first();
        $department = Department::query()->create([
            'name' => $name,
            'code' => Str::upper(Str::slug($name, '_')),
            'department_template_id' => $template?->id,
            'department_category_id' => $template?->department_category_id,
            'status' => 'active',
        ]);
        OrganizationDepartment::query()->create([
            'department_id' => $department->id,
            'department_template_id' => $template?->id,
            'status' => 'active',
            'effective_on' => now()->toDateString(),
        ]);

        return $department;
    }

    private function designation(string $name, Department $department): void
    {
        if (Designation::query()->where('name', $name)->exists()) {
            return;
        }
        $template = DesignationTemplate::query()->where('name', $name)->first();
        $designation = Designation::query()->create([
            'name' => $name,
            'code' => Str::upper(Str::slug($name, '_')),
            'designation_template_id' => $template?->id,
            'department_id' => $department->id,
            'status' => 'active',
        ]);
        OrganizationDesignation::query()->create([
            'designation_id' => $designation->id,
            'designation_template_id' => $template?->id,
            'status' => 'active',
            'effective_on' => now()->toDateString(),
        ]);
        DesignationDepartmentMapping::query()->firstOrCreate([
            'designation_id' => $designation->id,
            'department_id' => $department->id,
        ]);
    }
}
