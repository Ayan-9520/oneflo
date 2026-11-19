<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Models\User;
use App\Support\SpreadsheetFile;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_hiring_records_the_legal_employer_and_a_department_change_keeps_the_previous_record(): void
    {
        [$organization, $user] = $this->member('hr-admin');
        $legal = $this->legalEntity($organization, 'Source Employer');
        $sales = $this->department($organization, 'Sales');
        $finance = $this->department($organization, 'Finance');
        $this->actingAs($user);

        $created = $this->postJson('/api/v1/employees', [
            'employee_code' => 'EMP-100',
            'given_name' => 'Asha',
            'family_name' => 'Rao',
            'email' => 'asha@example.com',
            'legal_entity_id' => $legal->id,
            'department_id' => $sales->id,
            'joined_on' => '2026-01-15',
            'organization_id' => $organization->id,
        ], $this->header($organization))->assertUnprocessable();

        $created = $this->postJson('/api/v1/employees', [
            'employee_code' => 'EMP-100',
            'given_name' => 'Asha',
            'family_name' => 'Rao',
            'email' => 'asha@example.com',
            'legal_entity_id' => $legal->id,
            'department_id' => $sales->id,
            'joined_on' => '2026-01-15',
        ], $this->header($organization))->assertCreated();

        $employeeId = $created->json('data.id');
        $this->assertDatabaseHas('employment_records', [
            'employee_id' => $employeeId,
            'legal_entity_id' => $legal->id,
            'department_id' => $sales->id,
            'change_type' => 'hire',
        ]);

        $this->patchJson('/api/v1/employees/'.$employeeId, [
            'department_id' => $finance->id,
            'effective_on' => '2026-04-01',
        ], $this->header($organization))->assertOk()->assertJsonPath('data.department.id', $finance->id);

        $records = EmploymentRecord::query()->where('employee_id', $employeeId)->orderBy('id')->get();
        $this->assertCount(2, $records);
        $this->assertSame($sales->id, $records[0]->department_id);
        $this->assertSame('2026-04-01', $records[0]->ended_on->toDateString());
        $this->assertSame($finance->id, $records[1]->department_id);
        $this->assertSame('department_change', $records[1]->change_type);
        $this->assertSame($legal->id, $records[1]->legal_entity_id);
    }

    public function test_a_transfer_opens_a_new_employment_and_keeps_the_source_legal_employer(): void
    {
        [$source, $user] = $this->member('hr-admin');
        $target = Organization::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $target->id,
            'user_id' => $user->id,
            'role_id' => Role::query()->where('slug', 'hr-admin')->firstOrFail()->id,
        ]);
        $sourceLegal = $this->legalEntity($source, 'Source Employer');
        $targetLegal = $this->legalEntity($target, 'Target Employer');
        $this->actingAs($user);

        $employeeId = $this->postJson('/api/v1/employees', [
            'employee_code' => 'EMP-200',
            'given_name' => 'Kabir',
            'family_name' => 'Shah',
            'legal_entity_id' => $sourceLegal->id,
        ], $this->header($source))->assertCreated()->json('data.id');

        $this->postJson('/api/v1/employees/'.$employeeId.'/transfer', [
            'target_organization_id' => $target->id,
            'legal_entity_id' => $targetLegal->id,
            'employee_code' => 'EMP-200-T',
            'effective_on' => '2026-06-01',
        ], $this->header($source))->assertCreated();

        $sourceEmployee = Employee::query()->findOrFail($employeeId);
        $this->assertSame('transferred', $sourceEmployee->status);
        $this->assertSame($sourceLegal->id, EmploymentRecord::query()->where('employee_id', $employeeId)->orderBy('id')->first()->legal_entity_id);

        $moved = Employee::withoutGlobalScope('organization')->where('organization_id', $target->id)->where('employee_code', 'EMP-200-T')->firstOrFail();
        $this->assertSame($sourceEmployee->person_key, $moved->person_key);
        $this->assertSame($targetLegal->id, $moved->legal_entity_id);
        $this->assertSame('transfer', EmploymentRecord::withoutGlobalScope('organization')->where('employee_id', $moved->id)->value('change_type'));

        $history = $this->getJson('/api/v1/employees/'.$employeeId.'/history', $this->header($source))->assertOk()->json('data');
        $this->assertCount(3, $history);
        $this->assertEqualsCanonicalizing([$source->id, $source->id, $target->id], collect($history)->pluck('organization_id')->all());
    }

    public function test_a_transfer_is_refused_when_the_actor_cannot_enter_the_target_business(): void
    {
        [$source, $user] = $this->member('hr-admin');
        $target = Organization::factory()->create();
        $targetLegal = $this->legalEntity($target, 'Target Employer');
        $this->actingAs($user);
        $employeeId = $this->postJson('/api/v1/employees', [
            'employee_code' => 'EMP-300',
            'given_name' => 'Meera',
            'family_name' => 'Iyer',
        ], $this->header($source))->assertCreated()->json('data.id');

        $this->postJson('/api/v1/employees/'.$employeeId.'/transfer', [
            'target_organization_id' => $target->id,
            'legal_entity_id' => $targetLegal->id,
            'employee_code' => 'EMP-300-T',
        ], $this->header($source))->assertForbidden();

        $this->assertSame('active', Employee::query()->findOrFail($employeeId)->status);
        $this->assertNull(Employee::withoutGlobalScope('organization')->where('organization_id', $target->id)->first());
    }

    public function test_import_creates_employees_and_reports_unknown_departments(): void
    {
        [$organization, $user] = $this->member('hr-admin');
        $this->department($organization, 'Finance');
        $this->actingAs($user);
        $binary = SpreadsheetFile::binary(
            ['employee_code', 'given_name', 'family_name', 'email', 'phone', 'department', 'employment_type', 'joined_on', 'work_location'],
            [
                ['EMP-401', 'Ravi', 'Menon', 'ravi@example.com', '9000000001', 'Finance', 'permanent', '2026-02-01', 'Pune'],
                ['EMP-402', 'Nila', 'Das', '', '', 'Missing', 'permanent', '2026-02-01', ''],
            ],
        );

        $response = $this->post('/api/v1/employees/import', [
            'file' => UploadedFile::fake()->createWithContent('employees.xlsx', $binary),
        ], [...$this->header($organization), 'Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('data.created', 1);
        $this->assertCount(1, $response->json('data.errors'));
        $this->assertDatabaseHas('employees', [
            'organization_id' => $organization->id,
            'employee_code' => 'EMP-401',
            'email' => 'ravi@example.com',
            'work_location' => 'Pune',
        ]);
    }

    public function test_a_reporting_cycle_and_a_manager_from_another_business_are_rejected(): void
    {
        [$organization, $user] = $this->member('hr-admin');
        $other = Organization::factory()->create();
        $foreign = Employee::factory()->create(['organization_id' => $other->id]);
        $this->actingAs($user);
        $first = $this->postJson('/api/v1/employees', [
            'employee_code' => 'EMP-501',
            'given_name' => 'A',
            'family_name' => 'One',
        ], $this->header($organization))->assertCreated()->json('data.id');
        $second = $this->postJson('/api/v1/employees', [
            'employee_code' => 'EMP-502',
            'given_name' => 'B',
            'family_name' => 'Two',
            'manager_id' => $first,
        ], $this->header($organization))->assertCreated()->json('data.id');

        $this->patchJson('/api/v1/employees/'.$first, [
            'manager_id' => $second,
        ], $this->header($organization))->assertUnprocessable();
        $this->patchJson('/api/v1/employees/'.$first, [
            'manager_id' => $foreign->id,
        ], $this->header($organization))->assertUnprocessable();
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
            'role_id' => Role::query()->where('slug', $roleSlug)->firstOrFail()->id,
        ]);

        return [$organization, $user];
    }

    private function department(Organization $organization, string $name): Department
    {
        return Department::unguarded(fn () => Department::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
        ]));
    }

    private function legalEntity(Organization $organization, string $name): LegalEntity
    {
        return LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $organization->id,
            'legal_name' => $name,
        ]));
    }

    /**
     * @return array<string, string>
     */
    private function header(Organization $organization): array
    {
        return ['X-Organization-Id' => (string) $organization->id];
    }
}
