<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Models\User;
use App\Services\DomainOwnershipCheck;
use App\Services\OrganizationContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DomainMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->app->instance(DomainOwnershipCheck::class, new class implements DomainOwnershipCheck
        {
            public function owns(string $host, string $token): bool
            {
                return $host === 'hr.motorcart.in' && $token !== '';
            }
        });
    }

    public function test_only_verified_domains_select_an_organization(): void
    {
        [$motorcart, $admin] = $this->member('business-admin');
        $motorcart->update(['name' => 'Motorcart']);
        [$rupee] = $this->member('business-admin');
        $rupee->update(['name' => 'RupeeDial']);
        app(OrganizationContext::class)->forget();
        $motorEntity = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $motorcart->id,
            'legal_name' => 'Motorcart Private Limited',
        ]));
        $rupeeEntity = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $rupee->id,
            'legal_name' => 'RupeeDial Legal',
        ]));
        Employee::factory()->create([
            'organization_id' => $motorcart->id,
            'employee_code' => 'EMP-MC-001',
        ]);
        Employee::factory()->create([
            'organization_id' => $rupee->id,
            'employee_code' => 'EMP-RD-SECRET',
        ]);
        $super = User::factory()->create();
        $super->platformRoles()->sync([Role::query()->where('slug', 'super-admin')->value('id')]);

        $this->getJson('/api/v1/tenant-domain')
            ->assertOk()
            ->assertJsonPath('data.locked', false)
            ->assertJsonPath('data.organization', null);
        $this->getJson('/api/v1/tenant-domain', ['X-Forwarded-Host' => 'hr.motorcart.in'])
            ->assertOk()
            ->assertJsonPath('data.locked', false);
        $this->getJson('http://evil.example/api/v1/health')->assertStatus(421);
        $this->getJson('http://hr.motorcart.in/api/v1/tenant-domain')->assertStatus(421);

        $this->actingAs($admin);
        URL::forceRootUrl('http://localhost');
        $this->postJson('/api/v1/organization-domains', ['host' => 'app.oneflo.in'], $this->header($motorcart))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('host');
        $this->postJson('/api/v1/organization-domains', ['host' => 'hr.motorcart.in'], $this->header($rupee))
            ->assertForbidden();
        $created = $this->postJson('/api/v1/organization-domains', ['host' => 'hr.motorcart.in'], $this->header($motorcart))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');
        $domainId = (int) $created->json('data.id');
        $this->getJson('http://hr.motorcart.in/api/v1/tenant-domain')->assertStatus(421);

        $this->postJson('http://hr.rupeedial.com/api/v1/organization-domains/'.$domainId.'/verify', [], $this->header($motorcart))
            ->assertStatus(421);
        URL::forceRootUrl('http://localhost');
        $this->postJson('/api/v1/organization-domains/'.$domainId.'/verify', [], $this->header($motorcart))
            ->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $locked = 'http://hr.motorcart.in';
        $this->getJson($locked.'/api/v1/tenant-domain')
            ->assertOk()
            ->assertJsonPath('data.locked', true)
            ->assertJsonPath('data.organization.id', $motorcart->id)
            ->assertJsonPath('data.organization.name', 'Motorcart');

        $this->actingAs($super);
        $this->getJson($locked.'/api/v1/organizations')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Motorcart'])
            ->assertJsonMissing(['name' => 'RupeeDial']);
        $this->getJson($locked.'/api/v1/organizations/'.$rupee->id)->assertNotFound();
        $this->getJson($locked.'/api/v1/auth/me')
            ->assertOk()
            ->assertJsonMissing(['name' => 'RupeeDial'])
            ->assertJsonFragment(['name' => 'Motorcart']);
        URL::forceRootUrl('http://localhost');
        $this->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonFragment(['name' => 'RupeeDial'])
            ->assertJsonFragment(['name' => 'Motorcart']);

        $this->actingAs($admin);
        $this->getJson($locked.'/api/v1/employees', $this->header($rupee))->assertForbidden();
        $this->getJson($locked.'/api/v1/employees')
            ->assertOk()
            ->assertJsonFragment(['employee_code' => 'EMP-MC-001'])
            ->assertJsonMissing(['employee_code' => 'EMP-RD-SECRET']);

        $payrollAdmin = $this->attach($motorcart, 'payroll-admin');
        $this->actingAs($payrollAdmin);
        $this->postJson($locked.'/api/v1/payroll/runs', [
            'legal_entity_id' => $rupeeEntity->id,
            'year' => 2026,
            'month' => 10,
        ])->assertNotFound();
        $this->postJson($locked.'/api/v1/payroll/runs', [
            'legal_entity_id' => $motorEntity->id,
            'year' => 2026,
            'month' => 10,
        ])->assertCreated();
        $this->postJson($locked.'/api/v1/payroll/runs', [
            'legal_entity_id' => $motorEntity->id,
            'year' => 2026,
            'month' => 10,
        ])->assertUnprocessable();

        $this->actingAs($admin);
        $draft = [
            'document_type' => 'general_letter',
            'name' => 'Welcome',
            'body' => 'Hello',
        ];
        $this->postJson($locked.'/api/v1/document-previews', $draft + ['legal_entity_id' => $rupeeEntity->id])
            ->assertNotFound();
        $this->postJson($locked.'/api/v1/document-previews', $draft + ['legal_entity_id' => $motorEntity->id])
            ->assertOk()
            ->assertSee('Motorcart Private Limited', false)
            ->assertDontSee('RupeeDial Legal', false);

        $staff = $this->attach($motorcart, 'employee');
        $this->actingAs($staff);
        $this->postJson($locked.'/api/v1/organization-domains', ['host' => 'hr.other.example'])->assertForbidden();

        $this->artisan('backup:restore', ['path' => storage_path('app/private/backups/missing.sql')])->assertFailed();
        $this->artisan('backup:restore', [
            'path' => storage_path('app/private/backups/missing.sql'),
            '--confirm' => true,
        ])->assertFailed();
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function member(string $roleSlug): array
    {
        app(OrganizationContext::class)->forget();
        $organization = Organization::factory()->create();
        $user = $this->attach($organization, $roleSlug);

        return [$organization, $user];
    }

    private function attach(Organization $organization, string $roleSlug): User
    {
        app(OrganizationContext::class)->forget();
        $user = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => Role::query()->where('slug', $roleSlug)->value('id'),
        ]);

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function header(Organization $organization): array
    {
        return ['X-Organization-Id' => (string) $organization->id];
    }
}
