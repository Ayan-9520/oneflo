<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_a_user_cannot_read_another_organizations_brand(): void
    {
        [$organization, $user] = $this->member('hr-admin');
        $other = Organization::factory()->create();
        $foreignBrand = Brand::factory()->create(['organization_id' => $other->id]);
        $ownBrand = Brand::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($user);

        $this->getJson('/api/v1/brands/'.$foreignBrand->id, $this->organizationHeader($organization))
            ->assertNotFound();

        $this->getJson('/api/v1/brands/'.$ownBrand->id, $this->organizationHeader($organization))
            ->assertOk()
            ->assertJsonPath('data.id', $ownBrand->id);

        $this->getJson('/api/v1/brands', $this->organizationHeader($organization))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownBrand->id);
    }

    public function test_an_unassigned_organization_header_is_rejected(): void
    {
        [, $user] = $this->member('hr-admin');
        $other = Organization::factory()->create();

        $this->actingAs($user);

        $this->getJson('/api/v1/brands', $this->organizationHeader($other))->assertForbidden();
    }

    public function test_a_protected_request_without_organization_context_is_rejected(): void
    {
        [, $user] = $this->member('hr-admin');

        $this->actingAs($user);

        $this->getJson('/api/v1/brands')->assertStatus(422);
    }

    public function test_a_request_cannot_move_a_brand_to_another_organization(): void
    {
        [$organization, $user] = $this->member('business-admin');
        $other = Organization::factory()->create();

        $this->actingAs($user);

        $this->postJson('/api/v1/brands', [
            'name' => 'Northwind',
            'slug' => 'northwind',
            'organization_id' => $other->id,
        ], $this->organizationHeader($organization))->assertUnprocessable();

        $this->assertDatabaseMissing('brands', ['slug' => 'northwind']);

        app(OrganizationContext::class)->set($organization);
        $brand = new Brand(['name' => 'Forced', 'slug' => 'forced']);
        $brand->organization_id = $other->id;

        $this->expectException(AccessDeniedHttpException::class);
        $brand->save();
    }

    public function test_platform_admin_access_to_another_organization_is_audited(): void
    {
        $admin = User::factory()->create();
        $admin->platformRoles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());
        $organization = Organization::factory()->create();

        $this->actingAs($admin);

        $this->postJson('/api/v1/brands', [
            'name' => 'Platform Brand',
            'slug' => 'platform-brand',
        ], $this->organizationHeader($organization))->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'organization_id' => $organization->id,
            'action' => 'brand.created',
        ]);
        $this->assertInstanceOf(AuditLog::class, AuditLog::query()->first());
    }

    public function test_a_manager_only_sees_the_authorized_department(): void
    {
        [$organization, $manager] = $this->member('department-manager');
        $allowed = $this->department($organization, 'Finance');
        $blocked = $this->department($organization, 'Sales');
        $visible = Employee::factory()->create([
            'organization_id' => $organization->id,
            'department_id' => $allowed->id,
            'employee_code' => 'FIN-1',
        ]);
        $hidden = Employee::factory()->create([
            'organization_id' => $organization->id,
            'department_id' => $blocked->id,
            'employee_code' => 'SAL-1',
        ]);

        OrganizationAccess::query()->where('user_id', $manager->id)->firstOrFail()->scopes()->create([
            'scope_type' => 'department',
            'scope_id' => $allowed->id,
        ]);

        $this->actingAs($manager);

        $this->getJson('/api/v1/employees', $this->organizationHeader($organization))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);

        $this->getJson('/api/v1/employees/'.$hidden->id, $this->organizationHeader($organization))
            ->assertNotFound();
    }

    public function test_an_employee_only_sees_their_own_record(): void
    {
        [$organization, $user] = $this->member('employee');
        $own = Employee::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'employee_code' => 'ME-1',
        ]);
        $otherUser = User::factory()->create();
        $other = Employee::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $otherUser->id,
            'employee_code' => 'THEM-1',
        ]);

        $this->actingAs($user);

        $this->getJson('/api/v1/employees', $this->organizationHeader($organization))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);

        $this->getJson('/api/v1/employees/'.$other->id, $this->organizationHeader($organization))
            ->assertNotFound();
    }

    public function test_access_assignment_uses_the_active_organization_and_is_audited(): void
    {
        [$organization, $admin] = $this->member('hr-admin');
        $other = Organization::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($admin);

        $this->postJson('/api/v1/access-assignments', [
            'user_id' => $target->id,
            'role' => 'employee',
            'organization_id' => $other->id,
        ], $this->organizationHeader($organization))->assertUnprocessable();

        $this->postJson('/api/v1/access-assignments', [
            'user_id' => $target->id,
            'role' => 'employee',
        ], $this->organizationHeader($organization))->assertCreated();

        $this->assertDatabaseHas('organization_accesses', [
            'organization_id' => $organization->id,
            'user_id' => $target->id,
        ]);
        $this->assertDatabaseMissing('organization_accesses', [
            'organization_id' => $other->id,
            'user_id' => $target->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'organization_id' => $organization->id,
            'action' => 'access.assigned',
        ]);
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

    /**
     * @return array<string, string>
     */
    private function organizationHeader(Organization $organization): array
    {
        return ['X-Organization-Id' => (string) $organization->id];
    }
}
