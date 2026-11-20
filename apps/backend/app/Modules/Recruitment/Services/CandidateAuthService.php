<?php

namespace App\Modules\Recruitment\Services;

use App\Models\Candidate;
use App\Models\CandidateAccount;
use App\Models\CandidateLoginCode;
use App\Models\CandidateToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CandidateAuthService
{
    private const PROVIDERS = [
        'google' => [
            'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token' => 'https://oauth2.googleapis.com/token',
            'userinfo' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'scope' => 'openid email profile',
        ],
        'linkedin' => [
            'authorize' => 'https://www.linkedin.com/oauth/v2/authorization',
            'token' => 'https://www.linkedin.com/oauth/v2/accessToken',
            'userinfo' => 'https://api.linkedin.com/v2/userinfo',
            'scope' => 'openid profile email',
        ],
    ];

    private const CODE_MINUTES = 10;

    private const CODE_ATTEMPTS = 5;

    /**
     * @return array{google: bool, linkedin: bool, phone: bool, email: bool}
     */
    public function methods(): array
    {
        return [
            'google' => $this->providerConfigured('google'),
            'linkedin' => $this->providerConfigured('linkedin'),
            'phone' => $this->smsAvailable(),
            'email' => true,
        ];
    }

    public function redirectUrl(string $provider): string
    {
        $this->requireProvider($provider);
        $state = Str::random(40);
        Cache::put('candidate-oauth:'.$state, $provider, now()->addMinutes(10));

        return self::PROVIDERS[$provider]['authorize'].'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config("oneflo.candidate_auth.{$provider}.client_id"),
            'redirect_uri' => $this->callbackUrl($provider),
            'scope' => self::PROVIDERS[$provider]['scope'],
            'state' => $state,
        ]);
    }

    /**
     * @return array{token: string, account: CandidateAccount}
     */
    public function completeOAuth(string $provider, string $code, string $state): array
    {
        $this->requireProvider($provider);
        if (Cache::pull('candidate-oauth:'.$state) !== $provider) {
            throw ValidationException::withMessages(['state' => ['This sign-in link has expired. Please try again.']]);
        }
        $endpoints = self::PROVIDERS[$provider];
        $token = Http::asForm()->acceptJson()->timeout(15)->post($endpoints['token'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->callbackUrl($provider),
            'client_id' => config("oneflo.candidate_auth.{$provider}.client_id"),
            'client_secret' => config("oneflo.candidate_auth.{$provider}.client_secret"),
        ]);
        $accessToken = $token->json('access_token');
        if (! $token->successful() || ! is_string($accessToken)) {
            throw ValidationException::withMessages(['code' => ['Sign-in with '.ucfirst($provider).' did not complete. Please try again.']]);
        }
        $profile = Http::withToken($accessToken)->acceptJson()->timeout(15)->get($endpoints['userinfo']);
        $subject = $profile->json('sub');
        if (! $profile->successful() || ! is_string($subject) || $subject === '') {
            throw ValidationException::withMessages(['code' => ['Your '.ucfirst($provider).' profile could not be read.']]);
        }
        $email = is_string($profile->json('email')) ? strtolower(trim($profile->json('email'))) : null;
        $emailVerified = $email !== null && filter_var($profile->json('email_verified'), FILTER_VALIDATE_BOOLEAN);
        $column = $provider.'_id';

        $account = CandidateAccount::query()->where($column, $subject)->first()
            ?? ($emailVerified ? CandidateAccount::query()->where('email', $email)->first() : null);
        if ($account === null) {
            if ($email !== null && CandidateAccount::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => ['An account with this email already exists. Sign in with an email code instead.']]);
            }
            $account = new CandidateAccount(['email' => $email]);
        }
        $account->{$column} = $subject;
        $account->full_name = $account->full_name ?: Str::limit(trim((string) ($profile->json('name') ?? $email ?? 'Candidate')), 160, '');
        $account->avatar_url = $account->avatar_url ?: (is_string($profile->json('picture')) ? Str::limit($profile->json('picture'), 500, '') : null);
        if ($emailVerified && $account->email === $email) {
            $account->email_verified_at ??= now();
        }

        return $this->signIn($account);
    }

    /**
     * Returns the code only on a local install whose mail or SMS output is
     * the log, so sign-in can be tested without a delivery provider.
     */
    public function sendCode(string $channel, string $destination): ?string
    {
        $destination = $this->normalize($channel, $destination);
        if ($channel === 'phone' && ! $this->smsAvailable()) {
            throw ValidationException::withMessages(['destination' => ['Mobile sign-in is not available yet. Please use email.']]);
        }
        $code = (string) random_int(100000, 999999);
        CandidateLoginCode::query()->where('channel', $channel)->where('destination', $destination)->whereNull('used_at')->update(['used_at' => now()]);
        CandidateLoginCode::query()->create([
            'channel' => $channel,
            'destination' => $destination,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_MINUTES),
        ]);
        $message = "Your OneFlo Careers sign-in code is {$code}. It expires in ".self::CODE_MINUTES.' minutes. Do not share it.';
        $undelivered = $channel === 'phone' || in_array(config('mail.default'), ['log', 'array'], true);
        if ($channel === 'email') {
            Mail::raw($message, fn ($mail) => $mail->to($destination)->subject('Your OneFlo Careers sign-in code'));
        } else {
            Log::info('Candidate sign-in code (log SMS driver)', ['phone' => $destination, 'code' => $code]);
        }

        return $undelivered && app()->environment('local') ? $code : null;
    }

    /**
     * @return array{token: string, account: CandidateAccount}
     */
    public function verifyCode(string $channel, string $destination, string $code, ?string $fullName): array
    {
        $destination = $this->normalize($channel, $destination);
        $row = $this->checkCode($channel, $destination, $code);
        $account = CandidateAccount::query()->where($channel, $destination)->first();
        if ($account === null) {
            $name = trim((string) $fullName);
            if ($name === '') {
                throw ValidationException::withMessages(['full_name' => ['Enter your full name to create your profile.']]);
            }
            $account = new CandidateAccount(['full_name' => Str::limit($name, 160, ''), $channel => $destination]);
        }
        $row->used_at = now();
        $row->save();
        $account->{$channel.'_verified_at'} ??= now();

        return $this->signIn($account);
    }

    /**
     * @param  array{full_name: string, email: string, password: string, phone: string, work_status: string, marketing_opt_in?: bool, code: string}  $input
     * @return array{token: string, account: CandidateAccount}
     */
    public function register(array $input): array
    {
        $email = $this->normalize('email', $input['email']);
        $phone = $this->normalize('phone', $input['phone']);
        if (CandidateAccount::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => ['A profile with this email already exists. Please sign in.']]);
        }
        if (CandidateAccount::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => ['This mobile number is already linked to another profile.']]);
        }
        $row = $this->checkCode('email', $email, $input['code']);
        $row->used_at = now();
        $row->save();

        return $this->signIn(new CandidateAccount([
            'full_name' => Str::limit(trim($input['full_name']), 160, ''),
            'email' => $email,
            'email_verified_at' => now(),
            'phone' => $phone,
            'password' => $input['password'],
            'work_status' => $input['work_status'],
            'marketing_opt_in' => (bool) ($input['marketing_opt_in'] ?? false),
            'terms_accepted_at' => now(),
        ]));
    }

    /**
     * @return array{token: string, account: CandidateAccount}
     */
    public function passwordLogin(string $email, string $password): array
    {
        $account = CandidateAccount::query()->where('email', strtolower(trim($email)))->first();
        if ($account === null || $account->password === null || ! Hash::check($password, $account->password)) {
            throw ValidationException::withMessages(['email' => ['The email or password is not correct.']]);
        }

        return $this->signIn($account);
    }

    private function checkCode(string $channel, string $destination, string $code): CandidateLoginCode
    {
        $row = CandidateLoginCode::query()->where('channel', $channel)->where('destination', $destination)
            ->whereNull('used_at')->where('expires_at', '>', now())->latest('id')->first();
        if ($row === null || $row->attempts >= self::CODE_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => ['This code has expired. Request a new one.']]);
        }
        if (! Hash::check($code, $row->code_hash)) {
            $row->increment('attempts');
            throw ValidationException::withMessages(['code' => ['The code is not correct.']]);
        }

        return $row;
    }

    public function resolve(?string $bearer): ?CandidateAccount
    {
        if ($bearer === null || $bearer === '') {
            return null;
        }
        $token = CandidateToken::query()->where('token_hash', hash('sha256', $bearer))->where('expires_at', '>', now())->first();
        if ($token === null) {
            return null;
        }
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        return $token->account;
    }

    public function signOut(string $bearer): void
    {
        CandidateToken::query()->where('token_hash', hash('sha256', $bearer))->delete();
    }

    /**
     * @return array{token: string, account: CandidateAccount}
     */
    private function signIn(CandidateAccount $account): array
    {
        $account->last_login_at = now();
        $account->save();
        $this->linkGuestApplications($account);
        $plain = Str::random(64);
        $account->tokens()->create([
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays((int) config('oneflo.candidate_auth.token_days', 30)),
        ]);

        return ['token' => $plain, 'account' => $account];
    }

    private function linkGuestApplications(CandidateAccount $account): void
    {
        $query = Candidate::query()->withoutGlobalScopes()->whereNull('candidate_account_id');
        $query->where(function ($where) use ($account): void {
            $where->whereRaw('1 = 0');
            if ($account->email !== null && $account->email_verified_at !== null) {
                $where->orWhereRaw('lower(email) = ?', [$account->email]);
            }
            if ($account->phone !== null && $account->phone_verified_at !== null) {
                $where->orWhere('phone', $account->phone);
            }
        });
        $query->update(['candidate_account_id' => $account->id]);
    }

    private function normalize(string $channel, string $destination): string
    {
        if ($channel === 'email') {
            $email = strtolower(trim($destination));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['destination' => ['Enter a valid email address.']]);
            }

            return $email;
        }
        $digits = preg_replace('/\D+/', '', $destination) ?? '';
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }
        if (! preg_match('/^[6-9]\d{9}$/', $digits)) {
            throw ValidationException::withMessages(['destination' => ['Enter a valid 10-digit mobile number.']]);
        }

        return $digits;
    }

    private function providerConfigured(string $provider): bool
    {
        return filled(config("oneflo.candidate_auth.{$provider}.client_id")) && filled(config("oneflo.candidate_auth.{$provider}.client_secret"));
    }

    private function requireProvider(string $provider): void
    {
        if (! isset(self::PROVIDERS[$provider]) || ! $this->providerConfigured($provider)) {
            abort(404, 'This sign-in method is not available.');
        }
    }

    private function smsAvailable(): bool
    {
        if (! config('oneflo.candidate_auth.phone_enabled')) {
            return false;
        }
        $driver = config('oneflo.candidate_auth.sms_driver');

        return $driver === 'log' ? app()->environment('local', 'testing') : false;
    }

    private function callbackUrl(string $provider): string
    {
        return config('oneflo.frontend_url').'/careers/auth/'.$provider;
    }
}
