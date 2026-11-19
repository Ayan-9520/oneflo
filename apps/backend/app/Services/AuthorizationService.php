<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\IssuedDocument;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthorizationService
{
    /** @var array<string, list<string>> */
    private array $permissionCache = [];

    public function __construct(private readonly OrganizationContext $context) {}

    public function canEnter(User $user, Organization $organization): bool
    {
        if ($organization->status !== 'active' && ! $user->hasPlatformRole()) {
            return false;
        }

        if ($user->hasPlatformRole()) {
            return true;
        }

        return $user->organizationAccesses()->where('organization_id', $organization->id)->exists();
    }

    public function hasPermission(User $user, string $permission, ?Organization $organization = null): bool
    {
        return in_array($permission, $this->permissions($user, $organization), true);
    }

    public function authorizePermission(User $user, string $permission): void
    {
        $organization = $this->context->get();

        if (! $this->hasPermission($user, $permission, $organization)) {
            throw new AccessDeniedHttpException('You do not have permission to perform this action.');
        }
    }

    /**
     * @return list<string>
     */
    public function permissions(User $user, ?Organization $organization = null): array
    {
        $key = $user->id.'|'.($organization?->id ?? 'platform');
        if (isset($this->permissionCache[$key])) {
            return $this->permissionCache[$key];
        }

        $this->loadAccess($user);
        $names = $user->platformRoles
            ->flatMap(fn ($role) => $role->permissions->pluck('name'))
            ->all();

        if ($organization !== null) {
            $orgNames = $user->organizationAccesses
                ->where('organization_id', $organization->id)
                ->flatMap(fn (OrganizationAccess $access) => $access->role?->permissions->pluck('name') ?? [])
                ->all();
            $names = [...$names, ...$orgNames];
        }

        return $this->permissionCache[$key] = array_values(array_unique($names));
    }

    public function authorizeIssuedDocument(User $user, IssuedDocument $document): void
    {
        $organization = $this->context->get();
        if (! $this->hasPermission($user, 'documents.view', $organization)) {
            throw new AccessDeniedHttpException('You do not have permission to perform this action.');
        }
        if ($this->hasPermission($user, 'employees.view', $organization) || $this->hasPermission($user, 'documents.generate', $organization)) {
            return;
        }
        $document->loadMissing('employee');
        if ($document->employee !== null && $document->employee->user_id === $user->id) {
            return;
        }
        throw new AccessDeniedHttpException('You do not have permission to perform this action.');
    }

    public function canViewEmployee(User $user, Employee $employee): bool
    {
        $organization = $this->context->get();

        if ($organization === null || $employee->organization_id !== $organization->id) {
            return false;
        }

        if ($this->hasUnscopedPermission($user, $organization, 'employees.view')) {
            return true;
        }

        if ($this->hasPermission($user, 'employees.view_team', $organization)) {
            return $this->employeeMatchesScope($user, $organization, $employee, 'employees.view_team');
        }

        if ($this->hasPermission($user, 'employees.view_own', $organization)) {
            return $employee->user_id === $user->id;
        }

        return false;
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function restrictEmployees(Builder $query, User $user): Builder
    {
        $organization = $this->context->get();

        if ($organization === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasUnscopedPermission($user, $organization, 'employees.view')) {
            return $query;
        }

        if ($this->hasPermission($user, 'employees.view_team', $organization)) {
            $departmentIds = $this->scopeIds($user, $organization, 'employees.view_team', 'department');

            return $query->whereIn('department_id', $departmentIds === [] ? [-1] : $departmentIds);
        }

        if ($this->hasPermission($user, 'employees.view_own', $organization)) {
            return $query->where('user_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    private function hasUnscopedPermission(User $user, Organization $organization, string $permission): bool
    {
        $this->loadAccess($user);
        if ($user->platformRoles->contains(fn ($role) => $role->permissions->contains('name', $permission))) {
            return true;
        }

        return $user->organizationAccesses
            ->where('organization_id', $organization->id)
            ->contains(fn (OrganizationAccess $access) => $access->scopes->isEmpty()
                && ($access->role?->permissions->contains('name', $permission) ?? false));
    }

    private function loadAccess(User $user): void
    {
        $user->loadMissing([
            'platformRoles.permissions:id,name',
            'organizationAccesses.role.permissions:id,name',
            'organizationAccesses.scopes',
        ]);
    }

    private function employeeMatchesScope(User $user, Organization $organization, Employee $employee, string $permission): bool
    {
        $departmentIds = $this->scopeIds($user, $organization, $permission, 'department');

        return $employee->department_id !== null && in_array($employee->department_id, $departmentIds, true);
    }

    /**
     * @return list<int>
     */
    private function scopeIds(User $user, Organization $organization, string $permission, string $scopeType): array
    {
        return $user->organizationAccesses()
            ->where('organization_id', $organization->id)
            ->whereHas('role.permissions', fn ($query) => $query->where('name', $permission))
            ->with('scopes')
            ->get()
            ->flatMap(fn (OrganizationAccess $access) => $access->scopes->where('scope_type', $scopeType)->pluck('scope_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
