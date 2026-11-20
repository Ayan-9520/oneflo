<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Candidate;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Modules\Organization\Services\EmployeeLifecycle;
use App\Services\OrganizationContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hires candidates flagged as test data as dummy employees, or removes them again.
 * Dummy employees are identified only by the TEST- employee code prefix.
 */
class DummyEmployees extends Command
{
    private const PREFIX = 'TEST-';

    protected $signature = 'oneflo:dummy-employees {--remove : Delete the dummy employees instead of creating them}';

    protected $description = 'Create (or remove) dummy employees from test-data candidates in every business';

    public function handle(EmployeeLifecycle $lifecycle, OrganizationContext $context): int
    {
        foreach (Organization::query()->orderBy('id')->get() as $organization) {
            $context->set($organization);
            try {
                $count = $this->option('remove') ? $this->remove() : $this->create($organization, $lifecycle);
                $this->line($organization->name.': '.$count.($this->option('remove') ? ' removed' : ' created'));
            } finally {
                $context->forget();
            }
        }

        return self::SUCCESS;
    }

    private function create(Organization $organization, EmployeeLifecycle $lifecycle): int
    {
        $legalEntityId = LegalEntity::query()->orderBy('id')->value('id');
        $created = 0;
        foreach (Candidate::query()->where('is_test_data', true)->orderBy('id')->get() as $candidate) {
            $code = self::PREFIX.strtoupper(substr((string) $organization->slug, 0, 4)).'-'.str_pad((string) $candidate->id, 3, '0', STR_PAD_LEFT);
            if (Employee::query()->where('employee_code', $code)->exists()) {
                continue;
            }
            $designation = $candidate->current_designation === null ? null
                : Designation::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($candidate->current_designation)])->first();
            $lifecycle->hire([
                'employee_code' => $code,
                'given_name' => $candidate->given_name,
                'family_name' => $candidate->family_name ?: '-',
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'legal_entity_id' => $legalEntityId,
                'department_id' => $designation?->department_id,
                'designation_id' => $designation?->id,
                'work_location' => $candidate->location,
                'note' => 'Dummy test employee from candidate #'.$candidate->id,
            ], null);
            $created++;
        }

        return $created;
    }

    private function remove(): int
    {
        $removed = 0;
        foreach (Employee::query()->where('employee_code', 'like', self::PREFIX.'%')->get() as $employee) {
            try {
                DB::transaction(function () use ($employee): void {
                    $employee->employmentRecords()->delete();
                    $employee->delete();
                });
                $removed++;
            } catch (\Throwable) {
                $this->warn($employee->employee_code.' has linked records (attendance, payroll, etc.) and was kept.');
            }
        }

        return $removed;
    }
}
