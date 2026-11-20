<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateAccount;
use App\Models\CandidateLoginCode;
use App\Models\Organization;
use App\Modules\Recruitment\Services\CandidateAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CandidateAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_code_creates_a_profile_and_links_guest_applications(): void
    {
        Mail::fake();
        $organization = Organization::factory()->create();
        $guest = Candidate::query()->withoutGlobalScopes()->forceCreate([
            'organization_id' => $organization->id,
            'given_name' => 'Asha',
            'family_name' => 'Rao',
            'email' => 'asha@example.com',
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/candidate/auth/code', ['channel' => 'email', 'destination' => 'Asha@Example.com'])->assertOk();
        $this->setCode('email', 'asha@example.com', '123456');
        $this->postJson('/api/v1/candidate/auth/code/verify', ['channel' => 'email', 'destination' => 'asha@example.com', 'code' => '000000', 'full_name' => 'Asha Rao'])->assertUnprocessable();
        $this->postJson('/api/v1/candidate/auth/code/verify', ['channel' => 'email', 'destination' => 'asha@example.com', 'code' => '123456'])
            ->assertUnprocessable()->assertJsonValidationErrors('full_name');
        $token = $this->postJson('/api/v1/candidate/auth/code/verify', ['channel' => 'email', 'destination' => 'asha@example.com', 'code' => '123456', 'full_name' => 'Asha Rao'])
            ->assertOk()->assertJsonPath('data.account.email_verified', true)->json('data.token');

        $this->assertSame(CandidateAccount::query()->value('id'), $guest->refresh()->candidate_account_id);
        $this->getJson('/api/v1/candidate/me', ['Authorization' => 'Bearer '.$token])->assertOk()->assertJsonPath('data.full_name', 'Asha Rao');
        $this->putJson('/api/v1/candidate/me', ['full_name' => 'Asha Rao', 'skills' => 'Excel'], ['Authorization' => 'Bearer '.$token])->assertOk()->assertJsonPath('data.skills', 'Excel');
        $this->postJson('/api/v1/candidate/auth/code/verify', ['channel' => 'email', 'destination' => 'asha@example.com', 'code' => '123456'])->assertUnprocessable();

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        $this->postJson('/api/v1/candidate/logout', [], ['Authorization' => 'Bearer '.$token])->assertOk();
        $this->getJson('/api/v1/candidate/me', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    }

    public function test_mobile_code_rejects_bad_numbers_and_locks_after_attempts(): void
    {
        $this->postJson('/api/v1/candidate/auth/code', ['channel' => 'phone', 'destination' => '9810012345'])->assertUnprocessable();
        config(['oneflo.candidate_auth.phone_enabled' => true]);
        $this->postJson('/api/v1/candidate/auth/code', ['channel' => 'phone', 'destination' => '12345'])->assertUnprocessable();
        $this->postJson('/api/v1/candidate/auth/code', ['channel' => 'phone', 'destination' => '+91 98100 12345'])->assertOk();
        $this->setCode('phone', '9810012345', '654321');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/candidate/auth/code/verify', ['channel' => 'phone', 'destination' => '9810012345', 'code' => '111111', 'full_name' => 'Neel'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/candidate/auth/code/verify', ['channel' => 'phone', 'destination' => '9810012345', 'code' => '654321', 'full_name' => 'Neel'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertSame(0, CandidateAccount::query()->count());
    }

    public function test_social_sign_in_needs_configuration_and_a_valid_state(): void
    {
        $this->getJson('/api/v1/candidate/auth/methods')->assertOk()->assertJsonPath('data.google', false)->assertJsonPath('data.linkedin', false);
        $this->getJson('/api/v1/candidate/auth/linkedin/redirect')->assertNotFound();

        config(['oneflo.candidate_auth.linkedin' => ['client_id' => 'id', 'client_secret' => 'secret']]);
        Http::fake([
            'www.linkedin.com/oauth/v2/accessToken' => Http::response(['access_token' => 'abc']),
            'api.linkedin.com/v2/userinfo' => Http::response(['sub' => 'li-1', 'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'email_verified' => true]),
        ]);
        $url = $this->getJson('/api/v1/candidate/auth/linkedin/redirect')->assertOk()->json('data.url');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->postJson('/api/v1/candidate/auth/linkedin/callback', ['code' => 'x', 'state' => 'forged'])->assertUnprocessable();
        $this->postJson('/api/v1/candidate/auth/linkedin/callback', ['code' => 'x', 'state' => $query['state']])
            ->assertOk()->assertJsonPath('data.account.full_name', 'Ravi Kumar')->assertJsonPath('data.account.linked.linkedin', true);
        $this->postJson('/api/v1/candidate/auth/linkedin/callback', ['code' => 'x', 'state' => $query['state']])->assertUnprocessable();
    }

    public function test_registration_verifies_email_and_enables_password_sign_in(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/candidate/auth/code', ['channel' => 'email', 'destination' => 'meera@example.com', 'purpose' => 'register'])->assertOk();
        $this->setCode('email', 'meera@example.com', '246810');
        $payload = [
            'full_name' => 'Meera Iyer',
            'email' => 'meera@example.com',
            'password' => 'secret123',
            'phone' => '9876543210',
            'work_status' => 'fresher',
            'marketing_opt_in' => true,
            'terms' => true,
            'code' => '246810',
        ];
        $this->postJson('/api/v1/candidate/auth/register', [...$payload, 'password' => 'short'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/v1/candidate/auth/register', [...$payload, 'terms' => false])->assertUnprocessable()->assertJsonValidationErrors('terms');
        $this->postJson('/api/v1/candidate/auth/register', $payload)->assertCreated()
            ->assertJsonPath('data.account.work_status', 'fresher')
            ->assertJsonPath('data.account.has_password', true)
            ->assertJsonMissingPath('data.account.password');
        $this->postJson('/api/v1/candidate/auth/register', $payload)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/candidate/auth/code', ['channel' => 'email', 'destination' => 'meera@example.com', 'purpose' => 'register'])->assertUnprocessable();

        $this->postJson('/api/v1/candidate/auth/password', ['email' => 'meera@example.com', 'password' => 'wrong123'])->assertUnprocessable();
        $token = $this->postJson('/api/v1/candidate/auth/password', ['email' => 'MEERA@example.com', 'password' => 'secret123'])->assertOk()->json('data.token');
        $this->getJson('/api/v1/candidate/me', ['Authorization' => 'Bearer '.$token])->assertOk()->assertJsonPath('data.phone', '9876543210');
    }

    public function test_code_requests_are_rate_limited(): void
    {
        Mail::fake();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/v1/candidate/auth/code', ['channel' => 'email', 'destination' => 'a'.$attempt.'@example.com'])->assertOk();
        }
        $this->postJson('/api/v1/candidate/auth/code', ['channel' => 'email', 'destination' => 'z@example.com'])->assertStatus(429);
    }

    private function setCode(string $channel, string $destination, string $code): void
    {
        CandidateLoginCode::query()->where('channel', $channel)->where('destination', $destination)->whereNull('used_at')
            ->update(['code_hash' => Hash::make($code)]);
        $this->assertTrue(app(CandidateAuthService::class)->methods()['email']);
    }
}
