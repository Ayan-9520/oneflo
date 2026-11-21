<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Models\SiteVisitor;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteVisitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_hiring_admin_can_read_visitor_details(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $organization = Organization::factory()->create();
        $admin = User::factory()->create();
        $outsider = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'role_id' => Role::query()->where('slug', 'hr-admin')->value('id'),
        ]);
        SiteVisitor::query()->create(['name' => 'Ayan', 'phone' => '9000000000', 'location' => 'Delhi', 'email' => 'ayan@example.com']);
        $header = ['X-Organization-Id' => (string) $organization->id];

        $this->actingAs($outsider)->getJson('/api/v1/site-visitors', $header)->assertForbidden();
        $this->actingAs($admin)->getJson('/api/v1/site-visitors', $header)->assertOk()->assertJsonFragment([
            'name' => 'Ayan',
            'phone' => '9000000000',
            'location' => 'Delhi',
            'email' => 'ayan@example.com',
        ]);
    }
}
