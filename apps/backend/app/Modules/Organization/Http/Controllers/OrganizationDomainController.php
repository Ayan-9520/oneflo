<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OrganizationDomain;
use App\Modules\Organization\Http\Resources\OrganizationDomainResource;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\DomainOwnershipCheck;
use App\Services\OrganizationContext;
use App\Support\ApiResponse;
use App\Support\HostName;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrganizationDomainController extends Controller
{
    public function index(Request $request, AuthorizationService $authorization): AnonymousResourceCollection
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');

        return OrganizationDomainResource::collection(
            OrganizationDomain::query()->orderBy('host')->get(),
        );
    }

    public function store(Request $request, AuthorizationService $authorization, AuditLogger $auditLogger, OrganizationContext $context): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $organization = $context->get();
        if ($organization === null) {
            abort(422, 'Organization context is required.');
        }
        $data = $request->validate([
            'host' => ['required', 'string', 'max:253'],
        ]);
        $host = $this->host($data['host']);
        $existing = OrganizationDomain::query()->withoutGlobalScopes()->where('host', $host)->first();
        if ($existing !== null && (int) $existing->organization_id !== (int) $organization?->id) {
            throw ValidationException::withMessages([
                'host' => 'This domain is already registered.',
            ]);
        }
        if ($existing !== null && $existing->status === 'verified') {
            throw ValidationException::withMessages([
                'host' => 'This domain is already verified.',
            ]);
        }

        $domain = $existing ?? new OrganizationDomain;
        $domain->host = $host;
        $domain->status = 'pending';
        $domain->verification_token = Str::lower(Str::random(40));
        $domain->verified_at = null;
        try {
            $domain->save();
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'host' => 'This domain is already registered.',
            ]);
        }

        $auditLogger->accessChange($request->user(), $organization, 'organization.domain_requested', [
            'host' => $host,
        ]);

        return (new OrganizationDomainResource($domain))->response()->setStatusCode(201);
    }

    public function verify(Request $request, OrganizationDomain $organizationDomain, AuthorizationService $authorization, AuditLogger $auditLogger, DomainOwnershipCheck $ownership, OrganizationContext $context): OrganizationDomainResource
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $organization = $context->get();
        if ($organization === null) {
            abort(422, 'Organization context is required.');
        }
        if ($organizationDomain->status === 'verified') {
            return new OrganizationDomainResource($organizationDomain);
        }

        if (! $ownership->owns($organizationDomain->host, $organizationDomain->verification_token)) {
            throw ValidationException::withMessages([
                'host' => 'The DNS TXT record oneflo-domain-verification was not found for this domain.',
            ]);
        }

        $organizationDomain->status = 'verified';
        $organizationDomain->verified_at = now();
        $organizationDomain->save();
        $auditLogger->accessChange($request->user(), $organization, 'organization.domain_verified', [
            'host' => $organizationDomain->host,
        ]);

        return new OrganizationDomainResource($organizationDomain);
    }

    public function destroy(Request $request, OrganizationDomain $organizationDomain, AuthorizationService $authorization, AuditLogger $auditLogger, OrganizationContext $context): JsonResponse
    {
        $authorization->authorizePermission($request->user(), 'organization.manage');
        $organization = $context->get();
        if ($organization === null) {
            abort(422, 'Organization context is required.');
        }
        $host = $organizationDomain->host;
        $organizationDomain->delete();
        $auditLogger->accessChange($request->user(), $organization, 'organization.domain_removed', [
            'host' => $host,
        ]);

        return ApiResponse::data(['deleted' => true]);
    }

    private function host(string $value): string
    {
        $host = HostName::normalize($value);
        if ($host === null || HostName::isAddress($host) || ! str_contains($host, '.')) {
            throw ValidationException::withMessages([
                'host' => 'Enter a domain name such as hr.example.com.',
            ]);
        }
        if (HostName::isPlatform($host)) {
            throw ValidationException::withMessages([
                'host' => 'The OneFlo login domains cannot be assigned to an organization.',
            ]);
        }

        return $host;
    }
}
