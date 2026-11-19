<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\PasswordResetLinkRequest;
use App\Modules\Identity\Http\Requests\ResetPasswordRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function sendLink(PasswordResetLinkRequest $request): JsonResponse
    {
        Password::sendResetLink(['email' => (string) $request->string('email')]);

        return ApiResponse::data([
            'message' => 'If the account exists, a password reset link has been sent.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->safe()->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error('The password reset token is invalid.', 422);
        }

        return ApiResponse::data(['message' => 'The password has been reset.']);
    }
}
