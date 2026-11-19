<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_starts_a_session_and_logout_ends_it(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('data.email', $user->email);

        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);

        $this->postJson('/api/v1/auth/logout')->assertOk();

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_failed_login_is_recorded_and_locks_the_account(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        $this->assertSame(5, (int) $user->refresh()->failed_login_attempts);
        $this->assertTrue($user->locked_until?->isFuture());
        $this->assertSame(5, LoginHistory::query()->where('email', $user->email)->where('successful', false)->count());

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(423);
    }

    public function test_inactive_accounts_cannot_sign_in(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('login_histories', [
            'user_id' => $user->id,
            'successful' => false,
            'failure_reason' => 'inactive',
        ]);
    }

    public function test_confirmed_two_factor_requires_a_valid_code(): void
    {
        $secret = Totp::generateSecret();
        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonPath('errors.one_time_password.0', 'A valid authentication code is required.');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'one_time_password' => Totp::currentCode($secret),
        ])->assertOk();
    }

    public function test_password_reset_replaces_the_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->postJson('/api/v1/auth/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Replacement-Password-12',
                'password_confirmation' => 'Replacement-Password-12',
            ])->assertOk();

            return true;
        });

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Replacement-Password-12',
        ])->assertOk();
    }

    public function test_email_verification_marks_the_account_verified(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->postJson('/api/v1/auth/email/verification-notification')->assertOk();
        Notification::assertSentTo($user, VerifyEmail::class);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);
        $parts = parse_url($url);

        $this->getJson($parts['path'].'?'.$parts['query'])->assertOk();
        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }
}
