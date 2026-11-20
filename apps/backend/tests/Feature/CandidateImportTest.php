<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Models\User;
use App\Support\Spreadsheet;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CandidateImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['full_name', 'email', 'phone', 'location', 'experience_years', 'current_company', 'skills', 'business', 'candidate_type', 'is_test_data', 'notes'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_valid_workbook_imports_dummy_candidates_without_employees_or_applications(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $this->actingAs($hr);
        $this->get('/api/v1/recruitment/candidate-imports/template', $this->header($organization))
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $rows = [self::HEADERS];
        for ($i = 1; $i <= 50; $i++) {
            $rows[] = ['Test Candidate '.$i, 'test'.$i.'@example.com', '+91 90000 '.str_pad((string) $i, 5, '0', STR_PAD_LEFT), 'Delhi', (string) ($i % 8), 'Fictional Co', 'Excel', $organization->name, 'DUMMY', 'YES', 'Fictional test data. Do not contact.'];
        }
        $preview = $this->upload($organization, $this->xlsx($rows))->assertCreated();
        $preview->assertJsonPath('data.summary.total', 50)
            ->assertJsonPath('data.summary.valid', 50)
            ->assertJsonPath('data.summary.importable', 50)
            ->assertJsonPath('data.summary.test_data', 50)
            ->assertJsonPath('data.rows.0.row', 2);
        $this->assertSame(0, Candidate::query()->withoutGlobalScopes()->count(), 'Preview must not save candidates.');

        $result = $this->postJson('/api/v1/recruitment/candidate-imports/'.$preview->json('data.id').'/confirm', [], $this->header($organization))->assertOk();
        $result->assertJsonPath('data.status', 'imported')
            ->assertJsonPath('data.summary.imported', 50)
            ->assertJsonPath('data.summary.failed', 0);

        $candidate = Candidate::query()->withoutGlobalScopes()->where('email', 'test1@example.com')->firstOrFail();
        $this->assertSame($organization->id, $candidate->organization_id);
        $this->assertTrue($candidate->is_test_data);
        $this->assertSame('Excel Import', $candidate->source);
        $this->assertSame('9000000001', $candidate->phone);
        $this->assertSame('Fictional test data. Do not contact.', $candidate->notes);
        $this->assertSame(0, Employee::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, JobApplication::query()->withoutGlobalScopes()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'candidate_import.previewed')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'candidate_import.confirmed')->where('organization_id', $organization->id)->exists());
        $this->getJson('/api/v1/recruitment/candidates', $this->header($organization))->assertOk()->assertJsonPath('data.0.is_test_data', true);

        $this->postJson('/api/v1/recruitment/candidate-imports/'.$preview->json('data.id').'/confirm', [], $this->header($organization))->assertUnprocessable();
        $this->assertSame(50, Candidate::query()->withoutGlobalScopes()->count());
    }

    public function test_invalid_rows_are_reported_with_excel_row_numbers_and_only_valid_rows_import(): void
    {
        [$organization, $hr] = $this->member('recruiter');
        $this->actingAs($hr);
        $csv = implode("\n", [
            'Name,Email Address,Mobile No,Experience,Business,Notes',
            'Asha Rao,asha@example.com,9810012345,3,,',
            ',nobody@example.com,9810012346,2,,',
            'Bad Email,not-an-email,9810012347,1,,',
            'Short Phone,short@example.com,12345,1,,',
            '',
            'Old Hand,old@example.com,9810012349,99,,',
            'Wrong Business,wrong@example.com,9810012350,1,Some Other Co,',
            'Formula,formula@example.com,98100,1,,=HYPERLINK("http://x")',
            'Ravi Kumar,ravi@example.com,09810012351,4,,',
        ]);
        $preview = $this->upload($organization, UploadedFile::fake()->createWithContent('candidates.csv', $csv))->assertCreated();
        $preview->assertJsonPath('data.summary.total', 8)
            ->assertJsonPath('data.summary.valid', 2)
            ->assertJsonPath('data.summary.invalid', 6);
        $rows = collect($preview->json('data.rows'))->keyBy('row');
        $this->assertSame('invalid', $rows[3]['status']);
        $this->assertContains('Full name is required.', $rows[3]['errors']);
        $this->assertStringContainsString('not a valid email', $rows[4]['errors'][0]);
        $this->assertStringContainsString('10 to 15 digits', $rows[5]['errors'][0]);
        $this->assertStringContainsString('between 0 and 60', $rows[7]['errors'][0]);
        $this->assertStringContainsString('is not the business you are importing into', $rows[8]['errors'][0]);

        $id = $preview->json('data.id');
        $report = $this->get('/api/v1/recruitment/candidate-imports/'.$id.'/errors', $this->header($organization))->assertOk()->getContent();
        $this->assertStringContainsString("'=HYPERLINK", (string) $report);
        $this->assertStringNotContainsString(',=HYPERLINK', (string) $report);

        $this->postJson('/api/v1/recruitment/candidate-imports/'.$id.'/confirm', [], $this->header($organization))
            ->assertOk()->assertJsonPath('data.summary.imported', 2)->assertJsonPath('data.summary.skipped', 6);
        $this->assertSame(['asha@example.com', 'ravi@example.com'], Candidate::query()->withoutGlobalScopes()->orderBy('email')->pluck('email')->all());
        $this->assertFalse(Candidate::query()->withoutGlobalScopes()->where('email', 'asha@example.com')->value('is_test_data'));
    }

    public function test_existing_and_in_file_duplicates_are_not_created_again(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $this->actingAs($hr);
        $existing = Candidate::unguarded(fn () => Candidate::query()->create([
            'organization_id' => $organization->id, 'given_name' => 'Asha', 'family_name' => 'Rao',
            'email' => 'Asha@Example.com', 'phone' => '9810012345', 'source' => 'Careers', 'status' => 'active',
        ]));
        $preview = $this->upload($organization, $this->xlsx([
            ['full_name', 'email', 'phone'],
            ['Asha Again', 'asha@example.com', ''],
            ['Phone Match', 'new@example.com', '+91-98100-12345'],
            ['Neha Singh', 'neha@example.com', '9810099999'],
            ['Neha Copy', 'NEHA@example.com', '9810088888'],
        ]))->assertCreated();
        $preview->assertJsonPath('data.summary.valid', 1)->assertJsonPath('data.summary.duplicate', 3);
        $this->assertStringContainsString('Excel row 4', collect($preview->json('data.rows'))->firstWhere('row', 5)['errors'][0]);

        $this->postJson('/api/v1/recruitment/candidate-imports/'.$preview->json('data.id').'/confirm', [], $this->header($organization))
            ->assertOk()->assertJsonPath('data.summary.imported', 1)->assertJsonPath('data.summary.duplicate', 3);
        $this->assertSame(2, Candidate::query()->withoutGlobalScopes()->count());
        $this->assertSame('Asha', $existing->fresh()->given_name);
        $this->assertSame('Careers', $existing->fresh()->source);
    }

    public function test_column_mapping_can_be_changed_before_import(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $this->actingAs($hr);
        $preview = $this->upload($organization, $this->xlsx([
            ['Applicant', 'Primary Mail', 'Cell'],
            ['Kiran Shah', 'kiran@example.com', '9810011111'],
        ]))->assertCreated();
        $preview->assertJsonPath('data.summary.invalid', 1);
        $id = $preview->json('data.id');
        $this->putJson('/api/v1/recruitment/candidate-imports/'.$id.'/mapping', ['mapping' => ['0' => 'full_name', '1' => 'email', '2' => 'email']], $this->header($organization))->assertUnprocessable();
        $this->putJson('/api/v1/recruitment/candidate-imports/'.$id.'/mapping', ['mapping' => ['0' => 'full_name', '1' => 'email', '2' => 'phone', '9' => 'notes', '1x' => 'skills']], $this->header($organization))
            ->assertOk()->assertJsonPath('data.summary.valid', 1)->assertJsonPath('data.rows.0.email', 'kiran@example.com');
    }

    public function test_unauthorized_users_cannot_import(): void
    {
        [$organization, $employee] = $this->member('employee');
        $this->actingAs($employee);
        $this->get('/api/v1/recruitment/candidate-imports/template', $this->header($organization))->assertForbidden();
        $this->upload($organization, $this->xlsx([['full_name', 'email'], ['A B', 'a@example.com']]))->assertForbidden();
        $this->assertSame(0, Candidate::query()->withoutGlobalScopes()->count());    }

    public function test_imports_are_confined_to_an_authorized_business(): void
    {
        [$mine, $hr] = $this->member('hr-admin');
        $other = Organization::factory()->create();
        $this->actingAs($hr);
        $this->upload($other, $this->xlsx([['full_name', 'email'], ['A B', 'a@example.com']]))->assertForbidden();

        $preview = $this->upload($mine, $this->xlsx([['full_name', 'email', 'organization_id'], ['A B', 'a@example.com', (string) $other->id]]))->assertCreated();
        $id = $preview->json('data.id');

        $groupHr = User::factory()->create();
        foreach ([$mine, $other] as $organization) {
            OrganizationAccess::query()->create(['organization_id' => $organization->id, 'user_id' => $groupHr->id, 'role_id' => Role::query()->where('slug', 'hr-admin')->value('id')]);
        }
        $this->actingAs($groupHr);
        $this->getJson('/api/v1/recruitment/candidate-imports/'.$id, $this->header($other))->assertNotFound();
        $this->postJson('/api/v1/recruitment/candidate-imports/'.$id.'/confirm', [], $this->header($other))->assertNotFound();
        $this->postJson('/api/v1/recruitment/candidate-imports/'.$id.'/confirm', [], $this->header($mine))->assertOk();
        $this->assertSame([$mine->id], Candidate::query()->withoutGlobalScopes()->pluck('organization_id')->all());
    }

    public function test_unsafe_or_unsupported_files_are_rejected(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $this->actingAs($hr);
        $this->upload($organization, UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf'))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->upload($organization, UploadedFile::fake()->create('old.xls', 10, 'application/vnd.ms-excel'))->assertUnprocessable()->assertJsonPath('errors.file.0', fn (string $message) => str_contains($message, '.xls files are not supported'));
        $this->upload($organization, UploadedFile::fake()->createWithContent('broken.xlsx', 'not a zip'))->assertUnprocessable();
        $this->upload($organization, UploadedFile::fake()->createWithContent('empty.csv', "full_name,email\n"))->assertUnprocessable();
        $this->upload($organization, UploadedFile::fake()->create('huge.csv', 6000, 'text/csv'))->assertUnprocessable();
        $this->assertSame(0, Candidate::query()->withoutGlobalScopes()->count());
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function xlsx(array $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('candidates.xlsx', Spreadsheet::xlsx($rows));
    }

    private function upload(Organization $organization, UploadedFile $file): \Illuminate\Testing\TestResponse
    {
        return $this->post('/api/v1/recruitment/candidate-imports', ['file' => $file], [...$this->header($organization), 'Accept' => 'application/json']);
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
