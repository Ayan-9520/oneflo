<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\TwoFactorCodeRequest;
use App\Support\ApiResponse;
use App\Support\Totp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function enable(Request $request): JsonResponse
    {
        $secret = Totp::generateSecret();
        $request->user()->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        return ApiResponse::data([
            'secret' => $secret,
            'otpauth_url' => Totp::provisioningUri($request->user()->email, $secret),
        ]);
    }

    public function confirm(TwoFactorCodeRequest $request): JsonResponse
    {
        $user = $request->user();
        $secret = $user->two_factor_secret;

        if ($secret === null || ! Totp::verify($secret, (string) $request->string('code'))) {
            return ApiResponse::error('The authentication code is invalid.', 422, [
                'code' => ['The authentication code is invalid.'],
            ]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return ApiResponse::data(['two_factor_enabled' => true]);
    }

    public function disable(TwoFactorCodeRequest $request): JsonResponse
    {
        $user = $request->user();
        $secret = $user->two_factor_secret;

        if ($user->two_factor_confirmed_at === null || $secret === null || ! Totp::verify($secret, (string) $request->string('code'))) {
            return ApiResponse::error('The authentication code is invalid.', 422, [
                'code' => ['The authentication code is invalid.'],
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return ApiResponse::data(['two_factor_enabled' => false]);
    }
}
