<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Modules\Organization\Http\Resources\BrandResource;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BrandResource::collection(Brand::query()->orderBy('name')->get());
    }

    public function show(Brand $brand, OrganizationContext $context): BrandResource
    {
        $this->assertCurrentOrganization($brand, $context);

        return new BrandResource($brand);
    }

    public function store(Request $request, AuthorizationService $authorization, AuditLogger $auditLogger, OrganizationContext $context): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'branding.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120'],
            'is_primary' => ['sometimes', 'boolean'],
            'organization_id' => ['prohibited'],
        ]);

        $brand = Brand::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'is_primary' => $data['is_primary'] ?? false,
        ]);

        $auditLogger->crossOrganization($request->user(), $context->get(), 'brand.created', $brand);

        return (new BrandResource($brand))->response()->setStatusCode(201);
    }

    public function update(Request $request, Brand $brand, AuthorizationService $authorization, AuditLogger $auditLogger, OrganizationContext $context): BrandResource
    {
        $authorization->authorizePermission($request->user(), 'branding.manage');
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'max:120'],
            'is_primary' => ['sometimes', 'boolean'],
            'organization_id' => ['prohibited'],
        ]);

        $this->assertCurrentOrganization($brand, $context);
        $brand->update(collect($data)->except('organization_id')->all());
        $auditLogger->crossOrganization($request->user(), $context->get(), 'brand.updated', $brand);

        return new BrandResource($brand->refresh());
    }

    private function assertCurrentOrganization(Brand $brand, OrganizationContext $context): void
    {
        if ($brand->organization_id !== $context->id()) {
            abort(404, 'The requested resource was not found.');
        }
    }
}
