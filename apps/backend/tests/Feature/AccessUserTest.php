<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_adds_a_user_to_businesses_they_manage_only(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        [$first, $second, $foreign] = Organization::factory()->count(3)->create()->all();
        $admin = User::factory()->create();
        foreach ([$first, $second] as $organization) {
            OrganizationAccess::query()->create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'role_id' => Role::query()->where('slug', 'hr-admin')->value('id')]);
        }
        $this->actingAs($admin);
        $header = ['X-Organization-Id' => (string) $first->id];
        $payload = ['name' => 'Approver', 'email' => 'Approver@Example.com', 'password' => 'secret123', 'role' => 'hr-admin'];

        $this->postJson('/api/v1/access/users', [...$payload, 'organization_ids' => [$first->id, $foreign->id]], $header)->assertUnprocessable();
        $this->postJson('/api/v1/access/users', [...$payload, 'role' => 'group-admin', 'organization_ids' => [$first->id]], $header)->assertUnprocessable();
        $this->postJson('/api/v1/access/users', [...$payload, 'organization_ids' => [$first->id, $second->id]], $header)->assertCreated()->assertJsonPath('data.created', true);

        $user = User::query()->where('email', 'approver@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(2, OrganizationAccess::query()->where('user_id', $user->id)->count());
        $this->getJson('/api/v1/access/users', $header)->assertOk()->assertJsonFragment(['email' => 'approver@example.com']);
    }
}
