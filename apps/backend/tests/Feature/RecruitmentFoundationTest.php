<?php

namespace Tests\Feature;

use App\Models\DocumentSequence;
use App\Models\Employee;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Models\User;
use App\Modules\Organization\Services\EmployeeCodeGenerator;
use App\Services\OrganizationContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecruitmentFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_requisition_approval_history_and_rejection_reason(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $entity = $this->entity($organization);
        $this->actingAs($hr);
        $id = $this->postJson('/api/v1/recruitment/requisitions', [
            'legal_entity_id' => $entity->id,
            'title' => 'Analyst',
            'headcount' => 1,
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/recruitment/requisitions/'.$id.'/submit', [], $this->header($organization))->assertOk();
        $this->postJson('/api/v1/recruitment/requisitions/'.$id.'/reject', [], $this->header($organization))->assertUnprocessable();
        $this->postJson('/api/v1/recruitment/requisitions/'.$id.'/reject', ['comments' => 'Headcount is not approved.'], $this->header($organization))->assertOk();
        $this->postJson('/api/v1/recruitment/requisitions/'.$id.'/openings', [], $this->header($organization))->assertUnprocessable();
        $history = $this->getJson('/api/v1/recruitment/requisitions/'.$id, $this->header($organization))->assertOk();
        $this->assertSame('rejected', $history->json('data.status'));
        $this->assertCount(2, $history->json('data.history'));
    }

    public function test_recruiter_cannot_approve_or_hire_and_acceptance_creates_no_employee(): void
    {
        [$organization, $recruiter] = $this->member('recruiter');
        $approver = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $approver->id,
            'role_id' => Role::query()->where('slug', 'hr-admin')->value('id'),
        ]);
        $entity = $this->entity($organization);
        $this->actingAs($recruiter);
        $requisition = $this->postJson('/api/v1/recruitment/requisitions', [
            'legal_entity_id' => $entity->id,
            'title' => 'Analyst',
            'headcount' => 1,
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/recruitment/requisitions/'.$requisition.'/submit', [], $this->header($organization))->assertOk();
        $this->postJson('/api/v1/recruitment/requisitions/'.$requisition.'/approve', [], $this->header($organization))->assertForbidden();
        $this->actingAs($approver);
        $this->postJson('/api/v1/recruitment/requisitions/'.$requisition.'/approve', [], $this->header($organization))->assertOk();
        $this->actingAs($recruiter);
        $opening = $this->postJson('/api/v1/recruitment/requisitions/'.$requisition.'/openings', [], $this->header($organization))->assertCreated()->json('data.id');
        $candidate = $this->postJson('/api/v1/recruitment/candidates', [
            'given_name' => 'Asha',
            'family_name' => 'Rao',
        ], $this->header($organization))->assertCreated()->json('data.id');
        $application = $this->postJson('/api/v1/recruitment/applications', [
            'job_opening_id' => $opening,
            'candidate_id' => $candidate,
        ], $this->header($organization))->assertCreated()->json('data.id');
        $offer = $this->postJson('/api/v1/recruitment/offers', [
            'job_application_id' => $application,
            'legal_entity_id' => $entity->id,
            'designation' => 'Analyst',
            'compensation_note' => 'As discussed',
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/recruitment/offers/'.$offer.'/submit', [], $this->header($organization))->assertOk();
        $this->postJson('/api/v1/recruitment/offers/'.$offer.'/approve', [], $this->header($organization))->assertForbidden();
        $this->actingAs($approver);
        $this->postJson('/api/v1/recruitment/offers/'.$offer.'/approve', [], $this->header($organization))->assertOk();
        $this->actingAs($recruiter);
        $this->assertSame(0, Employee::query()->count());
        $this->postJson('/api/v1/recruitment/offers/'.$offer.'/accept', [], $this->header($organization))->assertOk();
        $this->assertSame(0, Employee::query()->count());
        $this->postJson('/api/v1/recruitment/offers/'.$offer.'/convert', ['joined_on' => '2026-12-15'], $this->header($organization))->assertForbidden();
        $employee = Employee::factory()->create(['organization_id' => $organization->id]);
        $this->post('/api/v1/employees/'.$employee->id.'/files', [
            'category' => 'aadhaar',
            'label' => 'Aadhaar',
            'reference_last4' => '1234',
            'retention_until' => '2027-01-01',
            'file' => UploadedFile::fake()->create('aadhaar.pdf', 12, 'application/pdf'),
        ], $this->header($organization))->assertForbidden();
    }

    public function test_group_admin_recruitment_follows_assigned_businesses(): void
    {
        [$assigned, $admin] = $this->member('group-admin');
        $other = Organization::factory()->create();
        $entity = $this->entity($assigned);
        $this->actingAs($admin);
        $this->postJson('/api/v1/recruitment/requisitions', [
            'legal_entity_id' => $entity->id,
            'title' => 'Analyst',
            'headcount' => 1,
        ], $this->header($assigned))->assertCreated();
        $this->getJson('/api/v1/recruitment/requisitions', $this->header($other))->assertForbidden();
    }

    public function test_employee_codes_use_the_employer_prefix_and_roll_back(): void
    {
        [$organization] = $this->member('hr-admin');
        app(OrganizationContext::class)->set($organization);
        $entity = $this->entity($organization);
        $entity->employee_code_prefix = 'RD';
        $entity->employee_code_padding = 5;
        $entity->save();
        Employee::factory()->create(['organization_id' => $organization->id, 'employee_code' => 'EMP-MC-001']);
        $generator = app(EmployeeCodeGenerator::class);
        $this->assertSame('RD-00001', $generator->next($entity));
        $this->assertSame(1, Employee::query()->where('employee_code', 'EMP-MC-001')->count());
        $before = DocumentSequence::query()->where('series', 'employee-entity-'.$entity->id)->value('last_number');
        try {
            DB::transaction(function () use ($generator, $entity): void {
                $generator->next($entity);
                throw new \RuntimeException('stop');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame($before, DocumentSequence::query()->where('series', 'employee-entity-'.$entity->id)->value('last_number'));
    }

    private function entity(Organization $organization): LegalEntity
    {
        return LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $organization->id,
            'legal_name' => 'Employer',
        ]));
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function member(string $roleSlug): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => Role::query()->where('slug', $roleSlug)->value('id'),
        ]);

        return [$organization, $user];
    }

    /**
     * @return array<string, string>
     */
    private function header(Organization $organization): array
    {
        return ['X-Organization-Id' => (string) $organization->id];
    }
}
