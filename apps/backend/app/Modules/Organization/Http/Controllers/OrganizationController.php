<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Modules\Organization\Http\Resources\OrganizationResource;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    public function index(Request $request, AuthorizationService $authorization): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Organization::query()->orderBy('name');

        if (! $user->hasPlatformRole()) {
            $ids = $user->organizationAccesses()->pluck('organization_id');
            $query->whereIn('id', $ids);
        }

        $locked = app(OrganizationContext::class)->locked();
        if ($locked !== null) {
            $query->whereKey($locked->id);
        }

        return OrganizationResource::collection($query->get());
    }

    public function show(Request $request, Organization $organization, AuthorizationService $authorization): OrganizationResource
    {
        $locked = app(OrganizationContext::class)->locked();
        if ($locked !== null && $organization->id !== $locked->id) {
            abort(404, 'The requested resource was not found.');
        }

        if (! $authorization->canEnter($request->user(), $organization)) {
            abort(403, 'You are not allowed to perform this action.');
        }

        return new OrganizationResource($organization->load([
            'legalEntities:id,organization_id,legal_name',
            'brands:id,organization_id,name',
            'departments:id,organization_id,name',
            'designations:id,organization_id,name',
            'branches:id,organization_id,name',
        ]));
    }

    public function store(Request $request, AuthorizationService $authorization, AuditLogger $auditLogger): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:organizations,slug'],
        ]);

        $organization = Organization::query()->create([
            'platform_id' => \App\Models\Platform::query()->value('id'),
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'status' => 'active',
        ]);

        if ($request->user()->hasPlatformRole()) {
            $auditLogger->crossOrganization($request->user(), $organization, 'organization.created', $organization);
        }

        return (new OrganizationResource($organization))->response()->setStatusCode(201);
    }
}
