<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuthorizationService;
use App\Support\ApiResponse;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::ROLES_VIEW);

        $roles = Role::query()->with('permissions:id,name')->orderBy('scope')->orderBy('name')->get();

        return ApiResponse::data([
            'roles' => $roles->map(fn (Role $role) => [
                'name' => $role->name,
                'slug' => $role->slug,
                'scope' => $role->scope,
                'permissions' => $role->permissions->pluck('name')->values(),
            ]),
            'permissions' => Permission::query()->orderBy('name')->get(['name', 'description']),
        ]);
    }
}
