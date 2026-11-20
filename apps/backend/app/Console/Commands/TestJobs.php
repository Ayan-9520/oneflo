<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Designation;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Models\JobRequisition;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Modules\Recruitment\Services\RecruitmentService;
use App\Services\OrganizationContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Publishes a few clearly labelled [TEST] job openings per business, or removes them again.
 * Test openings are identified only by the [TEST] title prefix.
 */
class TestJobs extends Command
{
    private const PREFIX = '[TEST] ';

    protected $signature = 'oneflo:test-jobs {--per=3 : Openings per business} {--remove : Remove the test openings instead of creating them}';

    protected $description = 'Create (or remove) [TEST] public job openings in every business';

    public function handle(RecruitmentService $recruitment, OrganizationContext $context): int
    {
        foreach (Organization::query()->orderBy('id')->get() as $organization) {
            $context->set($organization);
            try {
                $count = $this->option('remove') ? $this->remove() : $this->create($recruitment, max(1, min(5, (int) $this->option('per'))));
                $this->line($organization->name.': '.$count.($this->option('remove') ? ' removed' : ' created'));
            } finally {
                $context->forget();
            }
        }

        return self::SUCCESS;
    }

    private function create(RecruitmentService $recruitment, int $per): int
    {
        $legalEntityId = LegalEntity::query()->orderBy('id')->value('id');
        if ($legalEntityId === null) {
            return 0;
        }
        $existing = JobOpening::query()->where('title', 'like', self::PREFIX.'%')->pluck('title')->all();
        $designations = Designation::query()->whereNotNull('department_id')->orderBy('id')->get()
            ->reject(fn (Designation $designation) => in_array(self::PREFIX.$designation->name, $existing, true))
            ->unique('department_id')->take(max(0, $per - count($existing)));
        $created = 0;
        foreach ($designations as $index => $designation) {
            $department = Department::query()->find($designation->department_id)?->name ?? 'Operations';
            DB::transaction(function () use ($recruitment, $designation, $department, $legalEntityId, $index): void {
                $requisition = JobRequisition::query()->create([
                    'legal_entity_id' => $legalEntityId,
                    'department_id' => $designation->department_id,
                    'designation_id' => $designation->id,
                    'title' => self::PREFIX.$designation->name,
                    'description' => "Test opening for {$designation->name} in the {$department} team. Use it to try the full hiring flow.",
                    'headcount' => 1,
                    'status' => 'approved',
                    'submitted_at' => now(),
                ]);
                $opening = $recruitment->openingFor($requisition);
                $opening->fill([
                    'is_public' => true,
                    'location' => ['Mumbai', 'Delhi', 'Bengaluru', 'Remote'][$index % 4],
                    'employment_type' => 'full_time',
                    'category' => $department,
                    'experience_min' => $index % 3,
                    'experience_max' => ($index % 3) + 3,
                    'salary_public' => false,
                    'show_headcount' => true,
                    'responsibilities' => "Own day-to-day {$designation->name} work for the {$department} team.\nCoordinate with your manager and peers on weekly goals.\nKeep records and reports up to date.",
                    'qualifications' => "Graduate in any relevant discipline.\nGood communication skills.",
                    'skills' => "Communication, Teamwork, MS Office, {$department}",
                    'benefits' => "Health insurance\nPaid leave\nLearning support",
                    'deadline_on' => now()->addDays(30)->toDateString(),
                    'published_at' => now(),
                ]);
                $opening->slug = Str::slug($opening->title).'-'.$opening->id;
                $opening->save();
            });
            $created++;
        }

        return $created;
    }

    private function remove(): int
    {
        $removed = 0;
        foreach (JobOpening::query()->where('title', 'like', self::PREFIX.'%')->get() as $opening) {
            if (JobApplication::query()->where('job_opening_id', $opening->id)->exists()) {
                $opening->forceFill(['is_public' => false, 'status' => 'closed'])->save();
                $this->warn($opening->title.' has applications, so it was closed and unpublished instead of deleted.');

                continue;
            }
            DB::transaction(function () use ($opening): void {
                $requisitionId = $opening->job_requisition_id;
                $opening->delete();
                JobRequisition::query()->whereKey($requisitionId)->where('title', 'like', self::PREFIX.'%')->delete();
            });
            $removed++;
        }

        return $removed;
    }
}
