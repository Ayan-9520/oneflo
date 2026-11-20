<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use App\Support\ApiResponse;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccessUserController extends Controller
{
    public function index(Request $request, AuthorizationService $authorization, OrganizationContext $context): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::USERS_MANAGE);
        $organization = $context->get();
        $rows = OrganizationAccess::query()->where('organization_id', $organization->id)->get()->map(function (OrganizationAccess $access): ?array {
            $user = User::query()->find($access->user_id);

            return $user === null ? null : [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => Role::query()->whereKey($access->role_id)->value('name'),
                'is_active' => (bool) $user->is_active,
            ];
        })->filter()->sortBy('name')->values()->all();

        return ApiResponse::data($rows);
    }

    public function store(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $actor = $request->user();
        $authorization->authorizePermission($actor, Permissions::USERS_MANAGE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['nullable', Password::min(8)],
            'role' => ['required', Rule::exists('roles', 'slug')->where('scope', 'organization')],
            'organization_ids' => ['required', 'array', 'min:1'],
            'organization_ids.*' => ['integer', 'distinct'],
        ]);
        $organizations = Organization::query()->whereIn('id', $data['organization_ids'])->get();
        if ($organizations->count() !== count($data['organization_ids'])
            || $organizations->contains(fn (Organization $organization) => ! $authorization->hasPermission($actor, Permissions::USERS_MANAGE, $organization))) {
            throw ValidationException::withMessages(['organization_ids' => ['You can only add users to businesses where you manage users.']]);
        }
        $email = mb_strtolower(trim($data['email']));
        $existing = User::query()->where('email', $email)->first();
        if ($existing === null && empty($data['password'])) {
            throw ValidationException::withMessages(['password' => ['Set a password for the new user.']]);
        }
        $role = Role::query()->with('permissions:id,name')->where('slug', $data['role'])->firstOrFail();
        foreach ($organizations as $organization) {
            if (array_diff($role->permissions->pluck('name')->all(), $authorization->permissions($actor, $organization)) !== []) {
                throw ValidationException::withMessages(['role' => ['You cannot give a role with more access than you have in '.$organization->name.'.']]);
            }
        }

        $user = DB::transaction(function () use ($existing, $data, $email, $organizations, $role): User {
            $user = $existing ?? User::query()->forceCreate([
                'name' => trim($data['name']),
                'email' => $email,
                'password' => Hash::make($data['password']),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            foreach ($organizations as $organization) {
                OrganizationAccess::query()->updateOrCreate(
                    ['organization_id' => $organization->id, 'user_id' => $user->id],
                    ['role_id' => $role->id],
                );
            }

            return $user;
        });
        foreach ($organizations as $organization) {
            $audit->accessChange($actor, $organization, 'user.access_granted', ['user_id' => $user->id, 'role' => $role->slug]);
        }

        return ApiResponse::data(['id' => $user->id, 'email' => $user->email, 'created' => $existing === null, 'businesses' => $organizations->count()], 201);
    }
}
