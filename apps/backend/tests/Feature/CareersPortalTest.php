<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\ReferralReward;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CareersPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        RateLimiter::clear('careers');
    }

    public function test_only_published_openings_are_public_and_applications_do_not_hire(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $organization->slug = 'northwind';
        $organization->save();
        $entity = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $organization->id,
            'legal_name' => 'Northwind',
        ]));
        $this->actingAs($hr);
        $requisition = $this->postJson('/api/v1/recruitment/requisitions', [
            'legal_entity_id' => $entity->id,
            'title' => 'Analyst',
            'headcount' => 2,
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/recruitment/requisitions/'.$requisition.'/submit', [], $this->header($organization))->assertOk();
        $this->postJson('/api/v1/recruitment/requisitions/'.$requisition.'/approve', [], $this->header($organization))->assertOk();
        $opening = $this->postJson('/api/v1/recruitment/requisitions/'.$requisition.'/openings', [], $this->header($organization))->assertCreated()->json('data.id');
        $this->getJson('/api/v1/careers')->assertOk()->assertJsonPath('data.jobs.data', []);
        $this->postJson('/api/v1/recruitment/openings/'.$opening.'/publish', [
            'location' => 'Delhi',
            'employment_type' => 'full_time',
            'category' => 'Technology & IT',
            'salary_public' => false,
            'experience_min' => 2,
        ], $this->header($organization))->assertOk();
        $this->getJson('/api/v1/careers?category=Technology%20%26%20IT&location=del&company=northwind&experience=3&q=Analyst')
            ->assertOk()->assertJsonPath('data.jobs.total', 1)
            ->assertJsonPath('data.categories.10', ['name' => 'Technology & IT', 'jobs' => 1]);
        $this->getJson('/api/v1/careers?category=Insurance')->assertOk()->assertJsonPath('data.jobs.total', 0);
        $this->getJson('/api/v1/careers?experience=1')->assertOk()->assertJsonPath('data.jobs.total', 0);
        $this->getJson('/api/v1/careers?page=2')->assertOk()->assertJsonPath('data.jobs.data', []);
        $this->getJson('/api/v1/careers/companies/unknown')->assertNotFound();
        $this->get('/api/v1/careers/companies/northwind/logo')->assertNotFound();
        $slug = $this->getJson('/api/v1/careers')->assertOk()->json('data.jobs.data.0.slug');
        $this->getJson('/api/v1/careers/companies/northwind')->assertOk()->assertJsonPath('data.jobs.data.0.slug', $slug);
        $this->get('/careers/jobs/'.$slug)->assertOk()->assertSee('og:title', false);
        $file = ['resume' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf')];
        $this->post('/api/v1/careers/jobs/'.$slug.'/apply', [
            ...$file,
            'full_name' => 'Asha Rao',
            'email' => 'asha@example.com',
            'phone' => '9810012345',
            'location' => 'Delhi',
            'experience_years' => 3,
            'notice_period' => '30 days',
            'qualification' => 'BCom',
            'skills' => 'Excel',
            'consent' => '1',
            'accurate' => '1',
            'organization_id' => 999,
        ])->assertUnprocessable();
        $this->post('/api/v1/careers/jobs/'.$slug.'/apply', [
            'resume' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'),
            'full_name' => 'Asha Rao',
            'email' => 'asha@example.com',
            'phone' => '9810012345',
            'location' => 'Delhi',
            'experience_years' => 3,
            'notice_period' => '30 days',
            'qualification' => 'BCom',
            'skills' => 'Excel',
            'consent' => '1',
            'accurate' => '1',
        ])->assertCreated()->assertJsonPath('data.acknowledgement', 'not_sent');
        $this->assertSame(0, \App\Models\Employee::query()->count());
        $this->post('/api/v1/careers/jobs/'.$slug.'/apply', [
            'resume' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'),
            'full_name' => 'Asha Rao',
            'email' => 'asha@example.com',
            'phone' => '9810012345',
            'location' => 'Delhi',
            'experience_years' => 3,
            'notice_period' => '30 days',
            'qualification' => 'BCom',
            'skills' => 'Excel',
            'consent' => '1',
            'accurate' => '1',
        ])->assertUnprocessable();
        $this->assertSame(1, JobApplication::query()->count());
        $this->assertNotNull(JobApplication::query()->first()->consented_at);
        $this->postJson('/api/v1/recruitment/openings/'.$opening.'/close', ['status' => 'closed'], $this->header($organization))->assertOk();
        $this->getJson('/api/v1/careers')->assertOk()->assertJsonPath('data.jobs.total', 0);
        $this->getJson('/api/v1/careers/jobs/'.$slug)->assertNotFound();
    }

    public function test_public_applications_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/careers/jobs/missing/apply', [])->assertStatus(422);
        }
        $this->postJson('/api/v1/careers/jobs/missing/apply', [])->assertStatus(429);
    }

    public function test_referral_token_stays_with_the_employee_and_rewards_are_not_paid(): void
    {
        [$organization, $hr] = $this->member('hr-admin');
        $employeeUser = User::factory()->create();
        $employee = Employee::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $employeeUser->id,
            'employee_code' => 'EMP-KEEP',
        ]);
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $employeeUser->id,
            'role_id' => Role::query()->where('slug', 'employee')->value('id'),
        ]);
        $entity = LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $organization->id,
            'legal_name' => 'Northwind',
        ]));
        $this->actingAs($hr);
        $requisition = $this->postJson('/api/v1/recruitment/requisitions', [
            'legal_entity_id' => $entity->id,
            'title' => 'Analyst',
            'headcount' => 1,
        ], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/recruitment/requisitions/'.$requisition.'/submit', [], $this->header($organization))->assertOk();
        $this->postJson('/api/v1/recruitment/requisitions/'.$requisition.'/approve', [], $this->header($organization))->assertOk();
        $opening = $this->postJson('/api/v1/recruitment/requisitions/'.$requisition.'/openings', [], $this->header($organization))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/recruitment/openings/'.$opening.'/publish', [
            'location' => 'Delhi',
            'employment_type' => 'full_time',
            'category' => 'Sales & Telecalling',
        ], $this->header($organization))->assertOk();
        $this->actingAs($employeeUser);
        $token = $this->postJson('/api/v1/me/referrals', ['job_opening_id' => $opening], $this->header($organization))->assertCreated()->json('data.token');
        $this->assertGreaterThanOrEqual(32, strlen($token));
        $this->assertStringNotContainsString('EMP-KEEP', $token);
        $other = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $other->id,
            'role_id' => Role::query()->where('slug', 'employee')->value('id'),
        ]);
        $this->actingAs($other);
        $this->getJson('/api/v1/me/referrals', $this->header($organization))->assertOk()->assertJsonPath('data', []);
        $slug = \App\Models\JobOpening::query()->find($opening)->slug;
        $this->post('/api/v1/careers/jobs/'.$slug.'/apply', [
            'resume' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'),
            'full_name' => 'Neel Shah',
            'email' => 'neel@example.com',
            'phone' => '9810099999',
            'location' => 'Delhi',
            'experience_years' => 1,
            'notice_period' => '15 days',
            'qualification' => 'BA',
            'skills' => 'Calling',
            'consent' => '1',
            'accurate' => '1',
            'ref' => 'not-a-real-token',
        ])->assertUnprocessable();
        $this->post('/api/v1/careers/jobs/'.$slug.'/apply', [
            'resume' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'),
            'full_name' => 'Neel Shah',
            'email' => 'neel@example.com',
            'phone' => '9810099999',
            'location' => 'Delhi',
            'experience_years' => 1,
            'notice_period' => '15 days',
            'qualification' => 'BA',
            'skills' => 'Calling',
            'consent' => '1',
            'accurate' => '1',
            'ref' => $token,
        ])->assertCreated();
        $this->actingAs($hr);
        $policy = $this->postJson('/api/v1/recruitment/referral-policies', [
            'job_opening_id' => $opening,
            'enabled' => true,
            'amount' => 5000,
            'milestone' => 'joined',
        ], $this->header($organization))->assertOk();
        $reward = ReferralReward::query()->first();
        $this->assertNotNull($reward);
        $this->assertSame('unpaid', $reward->payout_status);
        $this->actingAs($employeeUser);
        $this->postJson('/api/v1/recruitment/referral-rewards/'.$reward->id.'/decision', [
            'status' => 'approved',
        ], $this->header($organization))->assertForbidden();
        $this->assertSame('unpaid', $reward->refresh()->payout_status);
        $this->assertNotNull($policy);
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
