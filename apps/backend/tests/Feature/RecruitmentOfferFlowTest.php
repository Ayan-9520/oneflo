<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateAccount;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentOfferFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sent_offer_reaches_the_candidate_who_accepts_it_before_joining(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $organization = Organization::factory()->create(['name' => 'Northwind']);
        $recruiter = $this->member($organization);
        $approver = $this->member($organization);
        $header = ['X-Organization-Id' => (string) $organization->id];
        $entity = LegalEntity::unguarded(fn () => LegalEntity::query()->create(['organization_id' => $organization->id, 'legal_name' => 'Northwind Engineering']));
        $account = CandidateAccount::query()->create(['full_name' => 'Meera Iyer', 'email' => 'meera@example.com']);
        $account->tokens()->create(['token_hash' => hash('sha256', 'meera-token'), 'expires_at' => now()->addDay()]);
        $outsider = CandidateAccount::query()->create(['full_name' => 'Other', 'email' => 'other@example.com']);
        $outsider->tokens()->create(['token_hash' => hash('sha256', 'other-token'), 'expires_at' => now()->addDay()]);
        $meera = ['Authorization' => 'Bearer meera-token'];

        $this->actingAs($recruiter);
        $requisition = $this->postJson('/api/v1/recruitment/requisitions', ['legal_entity_id' => $entity->id, 'title' => 'Analyst', 'headcount' => 1], $header)->json('data.id');
        $this->postJson("/api/v1/recruitment/requisitions/{$requisition}/submit", [], $header)->assertOk();
        $this->postJson("/api/v1/recruitment/requisitions/{$requisition}/approve", [], $header)->assertOk();
        $opening = $this->postJson("/api/v1/recruitment/requisitions/{$requisition}/openings", [], $header)->json('data.id');
        $candidate = $this->postJson('/api/v1/recruitment/candidates', ['given_name' => 'Meera', 'family_name' => 'Iyer', 'email' => 'meera@example.com'], $header)->json('data.id');
        Candidate::query()->whereKey($candidate)->update(['candidate_account_id' => $account->id]);
        $application = $this->postJson('/api/v1/recruitment/applications', ['job_opening_id' => $opening, 'candidate_id' => $candidate], $header)->json('data.id');
        $this->postJson("/api/v1/recruitment/applications/{$application}/stage", ['stage' => 'screening'], $header)->assertOk();
        $interview = $this->postJson("/api/v1/recruitment/applications/{$application}/interviews", ['scheduled_at' => '2026-12-01 10:00', 'interviewer' => 'Manager', 'mode' => 'video'], $header)->json('data.id');
        $this->postJson("/api/v1/recruitment/interviews/{$interview}/feedback", ['rating' => 4, 'comments' => 'Good', 'recommendation' => 'advance'], $header)->assertOk();
        $offer = $this->postJson('/api/v1/recruitment/offers', ['job_application_id' => $application, 'legal_entity_id' => $entity->id, 'designation' => 'Analyst', 'compensation_note' => 'INR 6,00,000 per year'], $header)->json('data.id');
        $this->postJson("/api/v1/recruitment/applications/{$application}/stage", ['stage' => 'offer'], $header)->assertOk();

        $row = collect($this->getJson('/api/v1/recruitment/applications', $header)->assertOk()->json('data'))->firstWhere('id', $application);
        $this->assertSame('Analyst', $row['job_title']);
        $this->assertSame('meera@example.com', $row['email']);
        $this->assertSame(4, $row['interviews'][0]['rating']);
        $this->assertSame('draft', $row['offer']['status']);

        $this->getJson('/api/v1/candidate/me/offers', $meera)->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/recruitment/offers/{$offer}/submit", [], $header)->assertOk();
        $this->actingAs($approver);
        $this->postJson("/api/v1/recruitment/offers/{$offer}/approve", [], $header)->assertOk();
        $this->actingAs($recruiter);
        $this->postJson("/api/v1/recruitment/offers/{$offer}/send", [], $header)->assertOk();
        $hrLetter = $this->get("/api/v1/recruitment/offers/{$offer}/letter", $header)->assertOk();
        $this->assertStringStartsWith('%PDF', $hrLetter->getContent());

        $this->getJson('/api/v1/candidate/me/offers', $meera)->assertOk()->assertJsonPath('data.0.status', 'sent')->assertJsonPath('data.0.company', 'Northwind');
        $letter = $this->get("/api/v1/candidate/me/offers/{$offer}/letter", $meera)->assertOk();
        $this->assertStringStartsWith('%PDF', $letter->getContent());
        $this->get("/api/v1/candidate/me/offers/{$offer}/letter", ['Authorization' => 'Bearer other-token'])->assertNotFound();
        $this->postJson("/api/v1/candidate/me/offers/{$offer}/accept", [], ['Authorization' => 'Bearer other-token'])->assertNotFound();
        $this->postJson("/api/v1/candidate/me/offers/{$offer}/accept", [], $meera)->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->postJson("/api/v1/candidate/me/offers/{$offer}/decline", [], $meera)->assertUnprocessable();

        $this->postJson("/api/v1/recruitment/offers/{$offer}/convert", ['joined_on' => '2026-12-15'], $header)->assertCreated();
        $row = collect($this->getJson('/api/v1/recruitment/applications', $header)->json('data'))->firstWhere('id', $application);
        $this->assertSame('hired', $row['stage']);
    }

    private function member(Organization $organization): User
    {
        $user = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => Role::query()->where('slug', 'hr-admin')->value('id'),
        ]);

        return $user;
    }
}
