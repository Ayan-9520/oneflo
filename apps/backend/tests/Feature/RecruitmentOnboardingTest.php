<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompanyProfile;
use App\Models\Designation;
use App\Models\DocumentSequence;
use App\Models\Employee;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RecruitmentOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_a_candidate_moves_through_offer_approval_and_becomes_an_employee(): void
    {
        [$organization, $recruiter] = $this->member('hr-admin');
        $approver = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $approver->id,
            'role_id' => Role::query()->where('slug', 'hr-admin')->value('id'),
        ]);
        $entity = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $organization->id,
            'legal_name' => 'Northwind Engineering',
        ]));
        CompanyProfile::unguarded(fn () => CompanyProfile::query()->create([
            'organization_id' => $organization->id,
            'legal_entity_id' => $entity->id,
            'legal_company_name' => 'Northwind Engineering',
            'brand_name' => 'Northwind',
            'registered_address' => '12 Harbour Road',
        ]));
        $this->actingAs($recruiter);
        $requisition = $this->postJson('/api/v1/recruitment/requisitions', [
            'legal_entity_id' => $entity->id,
            'title' => 'Analyst',
            'description' => 'Analyse operations for the legal employer.',
            'headcount' => 1,
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/recruitment/requisitions/'.$requisition.'/submit', [], $this->header($organization))->assertOk();
        $this->postJson('/api/v1/recruitment/requisitions/'.$requisition.'/approve', [], $this->header($organization))->assertOk();
        $opening = $this->postJson('/api/v1/recruitment/requisitions/'.$requisition.'/openings', [], $this->header($organization))->assertCreated()->json('data.id');
        $candidate = $this->postJson('/api/v1/recruitment/candidates', [
            'given_name' => 'Meera',
            'family_name' => 'Iyer',
            'email' => 'meera.iyer@example.com',
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->post('/api/v1/recruitment/candidates/'.$candidate.'/resume', [
            'resume' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'),
        ], $this->header($organization))->assertCreated();
        $application = $this->postJson('/api/v1/recruitment/applications', [
            'job_opening_id' => $opening,
            'candidate_id' => $candidate,
        ], $this->header($organization))->assertCreated()->json('data.id');
        $interview = $this->postJson('/api/v1/recruitment/applications/'.$application.'/interviews', [
            'scheduled_at' => '2026-12-01 10:00:00',
            'interviewer' => 'Hiring manager',
            'mode' => 'video',
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/recruitment/interviews/'.$interview.'/feedback', [
            'rating' => 4,
            'comments' => 'Proceed to offer.',
            'recommendation' => 'advance',
        ], $this->header($organization))->assertOk();
        $offer = $this->postJson('/api/v1/recruitment/offers', [
            'job_application_id' => $application,
            'legal_entity_id' => $entity->id,
            'designation' => 'Analyst',
            'compensation_note' => 'As approved by the legal employer',
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/recruitment/offers/'.$offer.'/submit', [], $this->header($organization))->assertOk();
        $this->postJson('/api/v1/recruitment/offers/'.$offer.'/approve', [], $this->header($organization))->assertUnprocessable();
        $this->actingAs($approver);
        $this->postJson('/api/v1/recruitment/offers/'.$offer.'/approve', [], $this->header($organization))->assertOk();
        $this->actingAs($recruiter);
        $this->postJson('/api/v1/recruitment/offers/'.$offer.'/accept', [], $this->header($organization))->assertOk();
        $letter = $this->postJson('/api/v1/issued-documents', [
            'legal_entity_id' => $entity->id,
            'document_type' => 'offer_letter',
            'candidate_id' => $candidate,
        ], $this->header($organization))->assertCreated();
        $letter->assertJsonPath('data.oneflo_attribution', true);
        $pdf = $this->get('/api/v1/issued-documents/'.$letter->json('data.id').'/pdf', $this->header($organization))->assertOk();
        $this->assertStringContainsString('Northwind Engineering', $pdf->getContent());
        $this->assertStringContainsString('Powered by OneFlo', $pdf->getContent());
        $converted = $this->postJson('/api/v1/recruitment/offers/'.$offer.'/convert', [
            'joined_on' => '2026-12-15',
        ], $this->header($organization))->assertCreated();
        $converted->assertJsonPath('data.employee_code', 'EMP-00001');
        $employeeId = $converted->json('data.employee_id');
        $this->postJson('/api/v1/employees/'.$employeeId.'/onboarding', [
            'probation_ends_on' => '2027-06-15',
        ], $this->header($organization))->assertOk();
        $bank = $this->postJson('/api/v1/employees/'.$employeeId.'/bank-accounts', [
            'bank_name' => 'Example Bank',
            'ifsc' => 'EXAM0001234',
            'account_number' => '9988776655',
        ], $this->header($organization))->assertCreated();
        $this->assertStringContainsString('6655', $bank->json('data.account'));
        $this->assertStringNotContainsString('9988776655', json_encode($bank->json()));
        $experience = $this->postJson('/api/v1/issued-documents', [
            'legal_entity_id' => $entity->id,
            'document_type' => 'experience_certificate',
            'employee_id' => $employeeId,
        ], $this->header($organization))->assertCreated();
        $experience->assertJsonPath('data.oneflo_attribution', false);
        $official = $this->get('/api/v1/issued-documents/'.$experience->json('data.id').'/pdf', $this->header($organization))->assertOk();
        $this->assertStringNotContainsString('Powered by OneFlo', $official->getContent());
        $this->assertSame(1, Employee::query()->where('employee_code', 'EMP-00001')->count());
    }

    public function test_sensitive_identity_files_are_encrypted_retained_and_restricted(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        [$same, $executive] = [$organization, User::factory()->create()];
        OrganizationAccess::query()->create([
            'organization_id' => $same->id,
            'user_id' => $executive->id,
            'role_id' => Role::query()->where('slug', 'hr-executive')->value('id'),
        ]);
        $employee = Employee::factory()->create(['organization_id' => $organization->id, 'status' => 'active']);
        $this->actingAs($hr);
        $this->post('/api/v1/employees/'.$employee->id.'/files', [
            'category' => 'aadhaar',
            'label' => 'Aadhaar card',
            'reference_last4' => '1234',
            'file' => UploadedFile::fake()->create('aadhaar.pdf', 12, 'application/pdf'),
        ], $this->header($organization))->assertUnprocessable();
        $created = $this->post('/api/v1/employees/'.$employee->id.'/files', [
            'category' => 'aadhaar',
            'label' => 'Aadhaar card',
            'reference_last4' => '1234',
            'retention_until' => '2027-01-01',
            'file' => UploadedFile::fake()->create('aadhaar.pdf', 12, 'application/pdf'),
        ], $this->header($organization))->assertCreated();
        $list = $this->getJson('/api/v1/employees/'.$employee->id.'/files', $this->header($organization))->assertOk();
        $this->assertSame('XXXX1234', $list->json('data.0.reference'));
        $this->assertStringNotContainsString('aadhaar.pdf', json_encode($list->json()));
        $download = $this->get('/api/v1/employee-files/'.$created->json('data.id'), $this->header($organization))->assertOk();
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $download->headers->get('Cache-Control'));
        $this->actingAs($executive);
        $this->get('/api/v1/employee-files/'.$created->json('data.id'), $this->header($organization))->assertForbidden();
        $this->getJson('/api/v1/employees/'.$employee->id.'/files', $this->header($organization))->assertOk()->assertJsonPath('data', []);
    }

    public function test_another_business_cannot_open_a_foreign_requisition(): void
    {
        [$organization, $user] = $this->member('hr-admin');
        $other = Organization::factory()->create();
        $foreign = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $other->id,
            'legal_name' => 'Other Employer',
        ]));
        $this->actingAs($user);
        $this->postJson('/api/v1/recruitment/requisitions', [
            'legal_entity_id' => $foreign->id,
            'title' => 'Analyst',
            'headcount' => 1,
        ], $this->header($organization))->assertNotFound();
    }

    public function test_a_voided_letter_keeps_its_number_and_blocks_download(): void
    {
        [$organization, $user] = $this->member('hr-admin');
        $entity = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $organization->id,
            'legal_name' => 'RupeeDial',
        ]));
        CompanyProfile::unguarded(fn () => CompanyProfile::query()->create([
            'organization_id' => $organization->id,
            'legal_entity_id' => $entity->id,
            'legal_company_name' => 'RupeeDial',
            'brand_name' => 'RupeeDial',
            'registered_address' => 'Head Office',
        ]));
        $employee = Employee::factory()->create([
            'organization_id' => $organization->id,
            'legal_entity_id' => $entity->id,
            'employee_code' => '001',
            'given_name' => 'Meera',
            'family_name' => 'Nair',
        ]);
        $this->actingAs($user);
        $header = $this->header($organization);
        $older = $this->postJson('/api/v1/issued-documents', [
            'legal_entity_id' => $entity->id,
            'document_type' => 'offer_letter',
            'employee_id' => $employee->id,
        ], $header)->assertCreated();
        $current = $this->postJson('/api/v1/issued-documents', [
            'legal_entity_id' => $entity->id,
            'document_type' => 'offer_letter',
            'employee_id' => $employee->id,
        ], $header)->assertCreated();
        $this->postJson('/api/v1/issued-documents/'.$older->json('data.id').'/void', [], $header)->assertOk()
            ->assertJsonPath('data.number', $older->json('data.number'));
        $this->get('/api/v1/issued-documents/'.$older->json('data.id').'/pdf', $header)->assertUnprocessable();
        $this->get('/api/v1/issued-documents/'.$current->json('data.id').'/pdf', $header)->assertOk();
        $list = $this->getJson('/api/v1/issued-documents?employee_id='.$employee->id, $header)->assertOk();
        $voided = collect($list->json('data'))->firstWhere('id', $older->json('data.id'));
        $this->assertNotNull($voided['voided_at']);
        $this->assertNull(collect($list->json('data'))->firstWhere('id', $current->json('data.id'))['voided_at']);
        $this->assertDatabaseHas('issued_documents', ['id' => $older->json('data.id'), 'number' => $older->json('data.number')]);
        $this->assertTrue(AuditLog::query()->where('action', 'document.voided')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'document.issued')->count() >= 2);
        $this->assertSame(2, (int) DocumentSequence::query()->where('organization_id', $organization->id)->where('series', 'OFFER_LETTER')->value('last_number'));
    }

    public function test_issued_letters_use_saved_fields_and_official_types_omit_the_footer(): void
    {
        [$organization, $user] = $this->member('hr-admin');
        $entity = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $organization->id,
            'legal_name' => 'RupeeDial',
        ]));
        CompanyProfile::unguarded(fn () => CompanyProfile::query()->create([
            'organization_id' => $organization->id,
            'legal_entity_id' => $entity->id,
            'legal_company_name' => 'RupeeDial',
            'brand_name' => 'RupeeDial',
            'registered_address' => 'Head Office',
            'contact_email' => 'hr@rupeedial.example',
        ]));
        $designation = Designation::unguarded(fn () => Designation::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Service advisor',
        ]));
        $employee = Employee::factory()->create([
            'organization_id' => $organization->id,
            'legal_entity_id' => $entity->id,
            'designation_id' => $designation->id,
            'employee_code' => 'RD-001',
            'given_name' => 'Meera',
            'family_name' => 'Nair',
            'joined_on' => '2026-11-18',
        ]);
        $this->actingAs($user);
        $header = $this->header($organization);
        $offer = $this->postJson('/api/v1/issued-documents', [
            'legal_entity_id' => $entity->id,
            'document_type' => 'offer_letter',
            'employee_id' => $employee->id,
        ], $header)->assertCreated();
        $offer->assertJsonPath('data.oneflo_attribution', true);
        $pdf = $this->get('/api/v1/issued-documents/'.$offer->json('data.id').'/pdf', $header)->assertOk();
        $this->assertStringContainsString('Offer of employment', $pdf->getContent());
        $this->assertStringContainsString('Service advisor', $pdf->getContent());
        $this->assertStringContainsString('18-11-2026', $pdf->getContent());
        $this->assertStringContainsString('not recorded', $pdf->getContent());
        $this->assertStringContainsString('hr@rupeedial.example', $pdf->getContent());
        $verification = $this->postJson('/api/v1/issued-documents', [
            'legal_entity_id' => $entity->id,
            'document_type' => 'employment_verification',
            'employee_id' => $employee->id,
        ], $header)->assertCreated();
        $verification->assertJsonPath('data.oneflo_attribution', false);
        $official = $this->get('/api/v1/issued-documents/'.$verification->json('data.id').'/pdf', $header)->assertOk();
        $this->assertStringNotContainsString('Powered by OneFlo', $official->getContent());
        $this->assertStringContainsString('EMP_VERIFY', $verification->json('data.number'));
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
