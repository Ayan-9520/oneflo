<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Models\User;
use App\Services\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $user->loadMissing([
            'platformRoles.permissions:id,name',
            'organizationAccesses.organization',
            'organizationAccesses.role.permissions:id,name',
        ]);

        $platformPermissions = $user->platformRoles
            ->flatMap(fn ($role) => $role->permissions->pluck('name'))
            ->unique()
            ->values();

        $organizations = $user->platformRoles->isNotEmpty()
            ? \App\Models\Organization::query()->where('status', 'active')->orderBy('name')->get()
            : $user->organizationAccesses->pluck('organization')->filter()->unique('id')->values();

        $locked = app(OrganizationContext::class)->locked();
        if ($locked !== null) {
            $organizations = $organizations->where('id', $locked->id)->values();
        }

        $accessByOrganization = $user->organizationAccesses->groupBy('organization_id');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'email_verified' => $user->hasVerifiedEmail(),
            'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            'platform_roles' => $user->platformRoles->pluck('slug')->values(),
            'platform_permissions' => $platformPermissions->all(),
            'organizations' => $organizations->map(function ($organization) use ($platformPermissions, $accessByOrganization) {
                $assigned = $accessByOrganization
                    ->get($organization->id, collect())
                    ->flatMap(fn ($access) => $access->role?->permissions->pluck('name') ?? []);

                return [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'permissions' => $platformPermissions->merge($assigned)->unique()->values()->all(),
                ];
            })->values(),
        ];
    }
}
