<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AttendanceDay;
use App\Models\AuthorizedSignatory;
use App\Models\CompanyProfile;
use App\Models\Employee;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\ReportSchedule;
use App\Models\Role;
use App\Models\SavedReportFilter;
use App\Models\User;
use App\Modules\Reports\ReportService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_every_export_stays_inside_the_active_business(): void
    {
        [$organization, $admin] = $this->member('business-admin');
        $entity = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $organization->id,
            'legal_name' => 'Northwind Engineering',
        ]));
        $own = Employee::factory()->create([
            'organization_id' => $organization->id,
            'legal_entity_id' => $entity->id,
            'employee_code' => 'OWN-100',
        ]);
        $other = Organization::factory()->create();
        $foreignEntity = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $other->id,
            'legal_name' => 'Foreign Employer',
        ]));
        $foreign = Employee::factory()->create([
            'organization_id' => $other->id,
            'legal_entity_id' => $foreignEntity->id,
            'employee_code' => 'FOREIGN-100',
        ]);
        AttendanceDay::unguarded(fn () => AttendanceDay::query()->create([
            'organization_id' => $organization->id, 'employee_id' => $own->id, 'work_date' => '2026-04-02', 'status' => 'present', 'late_minutes' => 0,
        ]));
        AttendanceDay::unguarded(fn () => AttendanceDay::query()->create([
            'organization_id' => $other->id, 'employee_id' => $foreign->id, 'work_date' => '2026-04-02', 'status' => 'present', 'late_minutes' => 0,
        ]));
        $run = PayrollRun::unguarded(fn () => PayrollRun::query()->create([
            'organization_id' => $organization->id, 'legal_entity_id' => $entity->id, 'year' => 2026, 'month' => 4, 'status' => 'locked', 'warnings' => [],
        ]));
        PayrollLine::unguarded(fn () => PayrollLine::query()->create([
            'organization_id' => $organization->id, 'payroll_run_id' => $run->id, 'employee_id' => $own->id,
            'earnings' => [], 'deductions' => ['tds' => 5000], 'employer' => [], 'gross_paise' => 100000, 'deduction_paise' => 5000, 'net_paise' => 95000,
        ]));
        $foreignFilter = SavedReportFilter::unguarded(fn () => SavedReportFilter::query()->create([
            'organization_id' => $other->id, 'user_id' => $admin->id, 'name' => 'Foreign view', 'report' => 'headcount', 'filters' => [],
        ]));

        $this->actingAs($admin);
        $dashboard = $this->getJson('/api/v1/reports/dashboard', $this->header($organization))->assertOk();
        $this->assertSame(1, $dashboard->json('data.headcount'));
        $query = ['from' => '2026-04-01', 'to' => '2027-03-31', 'employee_id' => $own->id];
        foreach (ReportService::REPORTS as $report) {
            $shown = $this->getJson('/api/v1/reports/'.$report.'?'.http_build_query($query), $this->header($organization))->assertOk();
            $this->assertStringNotContainsString('FOREIGN-100', json_encode($shown->json()));
            foreach (['csv', 'xlsx', 'pdf'] as $format) {
                $export = $this->get('/api/v1/reports/'.$report.'/export?'.http_build_query([...$query, 'format' => $format]), $this->header($organization));
                $export->assertOk();
                $export->assertHeader('X-Content-Type-Options', 'nosniff');
                $this->assertStringContainsString('private', (string) $export->headers->get('Cache-Control'));
                $this->assertStringContainsString('no-store', (string) $export->headers->get('Cache-Control'));
                $this->assertStringNotContainsString('FOREIGN-100', $export->getContent());
            }
        }
        $headcount = $this->get('/api/v1/reports/headcount/export?format=csv', $this->header($organization))->assertOk();
        $this->assertStringContainsString('OWN-100', $headcount->getContent());
        $tax = $this->get('/api/v1/reports/employee-tax/export?'.http_build_query([...$query, 'format' => 'pdf']), $this->header($organization))->assertOk();
        $this->assertStringContainsString('Northwind Engineering', $tax->getContent());
        $this->assertStringContainsString('not a Form 16', $tax->getContent());
        $this->assertStringNotContainsString('Powered by OneFlo', $tax->getContent());
        $this->getJson('/api/v1/reports/headcount?legal_entity_id='.$foreignEntity->id, $this->header($organization))->assertNotFound();
        $this->getJson('/api/v1/reports/headcount?employee_id='.$foreign->id, $this->header($organization))->assertNotFound();
        $this->getJson('/api/v1/reports/headcount?organization_id='.$other->id, $this->header($organization))->assertStatus(422);
        $this->getJson('/api/v1/reports/filters/'.$foreignFilter->id, $this->header($organization))->assertNotFound();

        Storage::fake('documents');
        $this->postJson('/api/v1/reports/schedules', [
            'report' => 'headcount',
            'filters' => [],
            'format' => 'csv',
            'cadence' => 'daily',
            'next_run_on' => now()->toDateString(),
        ], $this->header($organization))->assertCreated();
        $this->artisan('reports:run')->assertSuccessful();
        $this->artisan('reports:run')->assertSuccessful();
        $this->assertCount(1, Storage::disk('documents')->allFiles());
        $this->assertSame(1, AppNotification::query()->where('event', 'report.ready')->count());
        $this->assertTrue(ReportSchedule::query()->whereDate('next_run_on', '>', now()->toDateString())->exists());
    }

    public function test_official_documents_keep_their_prescribed_form(): void
    {
        [$organization, $admin] = $this->member('business-admin');
        $entity = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $organization->id,
            'legal_name' => 'Northwind Engineering',
        ]));
        $employee = Employee::factory()->create([
            'organization_id' => $organization->id,
            'legal_entity_id' => $entity->id,
            'employee_code' => 'OWN-200',
        ]);
        $this->actingAs($admin);
        $profile = $this->putJson('/api/v1/legal-entities/'.$entity->id.'/company-profile', [
            'legal_company_name' => 'Northwind Engineering Private Limited',
            'brand_name' => 'Northwind',
        ], $this->header($organization))->assertOk();
        $signatory = AuthorizedSignatory::query()->create([
            'company_profile_id' => $profile->json('data.id'),
            'name' => 'Meera Iyer',
            'designation' => 'Director',
            'is_primary' => true,
        ]);
        $official = $this->postJson('/api/v1/issued-documents', [
            'legal_entity_id' => $entity->id,
            'document_type' => 'experience_certificate',
            'employee_id' => $employee->id,
        ], $this->header($organization))->assertCreated();
        $pdf = $this->get('/api/v1/issued-documents/'.$official->json('data.id').'/pdf', $this->header($organization))->assertOk();
        $this->assertStringContainsString('Northwind Engineering', $pdf->getContent());
        $this->assertStringNotContainsString('Powered by OneFlo', $pdf->getContent());
        $this->post('/api/v1/issued-documents/'.$official->json('data.id').'/docx', [], $this->header($organization))->assertStatus(422);
        $signed = $this->postJson('/api/v1/issued-documents/'.$official->json('data.id').'/signatures', [
            'signatory_id' => $signatory->id,
        ], $this->header($organization))->assertCreated();
        $this->assertSame(64, strlen((string) $signed->json('data.content_sha256')));
        $signedPdf = $this->get('/api/v1/issued-documents/'.$official->json('data.id').'/pdf', $this->header($organization))->assertOk();
        $this->assertStringContainsString('Digitally signed by Meera Iyer', $signedPdf->getContent());
        $this->assertStringContainsString($signed->json('data.content_sha256'), $signedPdf->getContent());
        $this->assertStringNotContainsString('Powered by OneFlo', $signedPdf->getContent());
        $token = $official->json('data.verification_token');
        $verify = $this->getJson('/api/v1/document-verifications/'.$token)->assertOk();
        $this->assertSame($official->json('data.number'), $verify->json('data.number'));
        $this->assertArrayNotHasKey('html', $verify->json('data'));
        $this->getJson('/api/v1/document-verifications/unknown-token')->assertNotFound();
        $letter = $this->postJson('/api/v1/issued-documents', [
            'legal_entity_id' => $entity->id,
            'document_type' => 'general_letter',
            'employee_id' => $employee->id,
        ], $this->header($organization))->assertCreated();
        $docx = $this->post('/api/v1/issued-documents/'.$letter->json('data.id').'/docx', [], $this->header($organization))->assertOk();
        $this->assertStringStartsWith('PK', $docx->getContent());
        $letterPdf = $this->get('/api/v1/issued-documents/'.$letter->json('data.id').'/pdf', $this->header($organization))->assertOk();
        $this->assertStringContainsString('Powered by OneFlo', $letterPdf->getContent());
        $this->postJson('/api/v1/issued-documents', [
            'legal_entity_id' => $entity->id,
            'document_type' => 'experience_certificate',
            'employee_id' => $employee->id,
        ], $this->header($organization))->assertCreated();
        $versions = $this->getJson('/api/v1/issued-documents/'.$official->json('data.id').'/versions', $this->header($organization))->assertOk();
        $this->assertCount(2, $versions->json('data'));
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
