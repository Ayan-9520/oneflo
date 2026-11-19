<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\LoginHistory;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class AuthenticateUser
{
    public const INVALID = 'invalid';

    public const INACTIVE = 'inactive';

    public const LOCKED = 'locked';

    public const TWO_FACTOR = 'two_factor';

    /**
     * @return array{status: string, user: User|null}
     */
    public function execute(Request $request, string $email, string $password, ?string $oneTimePassword): array
    {
        $user = User::query()->where('email', $email)->first();
        $passwordMatches = Hash::check($password, $user->password ?? $this->dummyHash());

        if ($user === null || ! $passwordMatches) {
            $this->record($request, $user, $email, false, self::INVALID);
            if ($user !== null) {
                $this->registerFailure($user);
            }

            return ['status' => self::INVALID, 'user' => null];
        }

        if ($user->locked_until !== null && $user->locked_until->isFuture()) {
            $this->record($request, $user, $email, false, self::LOCKED);

            return ['status' => self::LOCKED, 'user' => null];
        }

        if (! $user->is_active) {
            $this->record($request, $user, $email, false, self::INACTIVE);

            return ['status' => self::INACTIVE, 'user' => null];
        }

        if ($user->two_factor_confirmed_at !== null) {
            $secret = $user->two_factor_secret;
            if ($oneTimePassword === null || $secret === null || ! Totp::verify($secret, $oneTimePassword)) {
                $this->record($request, $user, $email, false, self::TWO_FACTOR);

                return ['status' => self::TWO_FACTOR, 'user' => $user];
            }
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        Auth::login($user, false);
        $request->session()->regenerate();
        $this->record($request, $user, $email, true, null);

        return ['status' => 'ok', 'user' => $user];
    }

    private function registerFailure(User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;
        $user->forceFill([
            'failed_login_attempts' => $attempts,
            'locked_until' => $attempts >= 5 ? now()->addMinutes(15) : $user->locked_until,
        ])->save();
    }

    private function record(Request $request, ?User $user, string $email, bool $successful, ?string $reason): void
    {
        LoginHistory::query()->create([
            'user_id' => $user?->id,
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'successful' => $successful,
            'failure_reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('not-a-real-password');
    }
}
