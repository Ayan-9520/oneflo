<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\AuthenticateUser;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Resources\AccountResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AuthenticateUser $authenticateUser): JsonResponse
    {
        $result = $authenticateUser->execute(
            $request,
            (string) $request->string('email'),
            (string) $request->string('password'),
            $request->filled('one_time_password') ? (string) $request->string('one_time_password') : null,
        );

        return match ($result['status']) {
            'ok' => (new AccountResource($result['user']))->response(),
            AuthenticateUser::LOCKED => ApiResponse::error('This account is temporarily locked.', 423),
            AuthenticateUser::TWO_FACTOR => ApiResponse::error('A valid authentication code is required.', 422, [
                'one_time_password' => ['A valid authentication code is required.'],
            ]),
            default => ApiResponse::error('These credentials are not accepted.', 422, [
                'email' => ['These credentials are not accepted.'],
            ]),
        };
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::data(['status' => 'ok']);
    }

    public function me(Request $request): AccountResource
    {
        return new AccountResource($request->user());
    }
}
