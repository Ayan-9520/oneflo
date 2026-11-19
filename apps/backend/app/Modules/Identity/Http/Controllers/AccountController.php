<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Http\Requests\AccountActivationRequest;
use App\Services\AuthorizationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function updateActivation(AccountActivationRequest $request, User $user, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'users.manage');

        if ($request->user()->is($user) && $request->boolean('active') === false) {
            return ApiResponse::error('You cannot deactivate the signed-in account.', 422);
        }

        $active = $request->boolean('active');
        $user->forceFill([
            'is_active' => $active,
            'deactivated_at' => $active ? null : now(),
        ])->save();

        return ApiResponse::data([
            'id' => $user->id,
            'is_active' => $user->is_active,
        ]);
    }
}
