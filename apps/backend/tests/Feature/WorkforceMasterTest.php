<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentTemplate;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WorkforceMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceMasterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(WorkforceMasterSeeder::class);
    }

    public function test_templates_stay_global_until_a_business_activates_one(): void
    {
        [$organization, $user] = $this->member('group-admin');
        [$other] = $this->member('group-admin');
        $this->actingAs($user);
        $template = DepartmentTemplate::query()->where('name', 'Human Resources')->firstOrFail();

        $this->getJson('/api/v1/departments', $this->header($organization))->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/departments/adopt', ['template_ids' => [$template->id]], $this->header($organization))
            ->assertOk()
            ->assertJsonCount(1, 'data.activated_ids');

        $this->assertDatabaseHas('departments', ['organization_id' => $organization->id, 'name' => 'Human Resources', 'status' => 'active']);
        $this->assertDatabaseMissing('departments', ['organization_id' => $other->id, 'name' => 'Human Resources']);
        $this->postJson('/api/v1/departments', ['name' => 'Human Resources'], $this->header($organization))->assertUnprocessable();
    }

    public function test_archived_designation_stays_on_the_employee_and_drops_out_of_the_active_list(): void
    {
        [$organization, $user] = $this->member('group-admin');
        $this->actingAs($user);
        $created = $this->postJson('/api/v1/designations', ['name' => 'Loan Officer'], $this->header($organization))->assertCreated();
        $designationId = $created->json('data.id');
        $employee = Employee::unguarded(fn () => Employee::query()->create([
            'organization_id' => $organization->id,
            'employee_code' => 'EMP-M-1',
            'given_name' => 'Nia',
            'family_name' => 'Sen',
            'designation_id' => $designationId,
        ]));

        $this->deleteJson('/api/v1/designations/'.$designationId, [], $this->header($organization))->assertOk();
        $this->assertDatabaseHas('designations', ['id' => $designationId, 'status' => 'archived']);
        $this->assertSame($designationId, $employee->fresh()->designation_id);
        $active = $this->getJson('/api/v1/designations', $this->header($organization))->assertOk()->json('data');
        $this->assertNotContains($designationId, array_column($active, 'id'));
    }

    public function test_a_designation_cannot_point_at_another_business_department(): void
    {
        [$organization, $user] = $this->member('group-admin');
        [$other] = $this->member('group-admin');
        $foreign = Department::unguarded(fn () => Department::query()->create([
            'organization_id' => $other->id,
            'name' => 'Collections',
            'status' => 'active',
        ]));
        $this->actingAs($user);

        $this->postJson('/api/v1/designations', [
            'name' => 'Collection Executive',
            'department_id' => $foreign->id,
        ], $this->header($organization))->assertUnprocessable();
        $this->assertDatabaseMissing('designations', ['organization_id' => $organization->id, 'name' => 'Collection Executive']);
    }

    public function test_a_viewer_cannot_activate_templates(): void
    {
        [$organization, $user] = $this->member('employee');
        $this->actingAs($user);
        $template = DepartmentTemplate::query()->firstOrFail();

        $this->postJson('/api/v1/departments/adopt', ['template_ids' => [$template->id]], $this->header($organization))->assertForbidden();
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

    /**
     * @return array<string, string>
     */
    private function header(Organization $organization): array
    {
        return ['X-Organization-Id' => (string) $organization->id];
    }
}
