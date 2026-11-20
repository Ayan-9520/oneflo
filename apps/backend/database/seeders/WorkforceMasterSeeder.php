<?php

namespace Database\Seeders;

use App\Models\DepartmentCategory;
use App\Models\DepartmentTemplate;
use App\Models\DesignationTemplate;
use App\Models\EmploymentType;
use App\Models\JobGrade;
use App\Models\JobLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkforceMasterSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{departments: array<string, list<string>>, designations: array<string, list<string>>} $catalog */
        $catalog = require __DIR__.'/data/workforce_master.php';

        foreach ($catalog['departments'] as $category => $names) {
            $row = DepartmentCategory::query()->firstOrCreate(
                ['slug' => Str::slug($category)],
                ['name' => $category],
            );
            foreach ($names as $name) {
                DepartmentTemplate::query()->firstOrCreate([
                    'department_category_id' => $row->id,
                    'name' => $name,
                ]);
            }
        }

        foreach ($catalog['designations'] as $category => $names) {
            foreach ($names as $name) {
                DesignationTemplate::query()->firstOrCreate([
                    'category' => $category,
                    'name' => $name,
                ]);
            }
        }

        foreach (['Individual Contributor' => 1, 'Team Lead' => 2, 'Manager' => 3, 'Head' => 4, 'Director' => 5, 'Executive' => 6] as $name => $rank) {
            JobLevel::query()->firstOrCreate(
                ['organization_id' => null, 'name' => $name],
                ['rank' => $rank, 'status' => 'active'],
            );
        }

        foreach (['A' => 'Grade A', 'B' => 'Grade B', 'C' => 'Grade C'] as $code => $name) {
            JobGrade::query()->firstOrCreate(
                ['organization_id' => null, 'code' => $code],
                ['name' => $name, 'status' => 'active'],
            );
        }

        foreach (['full_time' => 'Full time', 'part_time' => 'Part time', 'contract' => 'Contract', 'intern' => 'Intern', 'consultant' => 'Consultant'] as $code => $name) {
            EmploymentType::query()->firstOrCreate(
                ['organization_id' => null, 'code' => $code],
                ['name' => $name, 'status' => 'active'],
            );
        }
    }
}
