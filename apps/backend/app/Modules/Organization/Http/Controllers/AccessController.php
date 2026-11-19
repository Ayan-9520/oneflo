<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Modules\Organization\Http\Resources\AuditLogResource;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccessController extends Controller
{
    public function store(Request $request, AuthorizationService $authorization, AuditLogger $auditLogger, OrganizationContext $context): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'access.manage');
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string', 'exists:roles,slug'],
            'scopes' => ['sometimes', 'array'],
            'scopes.*.type' => ['required_with:scopes', 'in:department,branch'],
            'scopes.*.id' => ['required_with:scopes', 'integer'],
            'organization_id' => ['prohibited'],
        ]);

        $role = Role::query()->where('slug', $data['role'])->where('scope', 'organization')->firstOrFail();
        $access = OrganizationAccess::query()->firstOrCreate([
            'organization_id' => $context->id(),
            'user_id' => $data['user_id'],
            'role_id' => $role->id,
        ]);

        foreach ($data['scopes'] ?? [] as $scope) {
            $access->scopes()->firstOrCreate([
                'scope_type' => $scope['type'],
                'scope_id' => $scope['id'],
            ]);
        }

        $auditLogger->accessChange($request->user(), $context->get(), 'access.assigned', [
            'user_id' => (int) $data['user_id'],
            'role' => $role->slug,
        ]);

        return ApiResponse::data([
            'id' => $access->id,
            'user_id' => $access->user_id,
            'role' => $role->slug,
        ], 201);
    }

    public function audit(AuthorizationService $authorization, OrganizationContext $context): AnonymousResourceCollection
    {
        $authorization->authorizePermission(request()->user(), 'access.manage');

        return AuditLogResource::collection(
            AuditLog::query()->where('organization_id', $context->id())->latest('id')->limit(100)->get(),
        );
    }
}
