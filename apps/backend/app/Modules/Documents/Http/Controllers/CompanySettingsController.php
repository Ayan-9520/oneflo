<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AuthorizedSignatory;
use App\Models\Brand;
use App\Models\BrandIdentity;
use App\Models\CompanyProfile;
use App\Models\LegalEntity;
use App\Models\OfficeAddress;
use App\Modules\Documents\Services\BrandingFileStore;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use App\Support\ApiResponse;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanySettingsController extends Controller
{
    public function showProfile(LegalEntity $legalEntity, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeView($authorization);
        $profile = CompanyProfile::query()->with(['offices', 'signatories'])->where('legal_entity_id', $legalEntity->id)->first();

        return ApiResponse::data($profile === null ? null : $this->profilePayload($profile));
    }

    public function updateProfile(Request $request, LegalEntity $legalEntity, AuthorizationService $authorization, AuditLogger $auditLogger, OrganizationContext $context): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::ORGANIZATION_MANAGE);
        $data = $request->validate($this->profileRules());
        $profile = CompanyProfile::query()->firstOrNew(['legal_entity_id' => $legalEntity->id]);
        $profile->fill($data);
        $profile->legal_entity_id = $legalEntity->id;
        $profile->save();
        $auditLogger->accessChange($request->user(), $context->get(), 'company_profile.saved', [
            'legal_entity_id' => $legalEntity->id,
        ]);

        return ApiResponse::data($this->profilePayload($profile->load(['offices', 'signatories'])));
    }

    public function uploadStamp(Request $request, CompanyProfile $profile, AuthorizationService $authorization, BrandingFileStore $files): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::BRANDING_MANAGE);
        $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $profile->stamp_path = $files->replace($profile->stamp_path, $request->file('file'), 'organizations/'.$profile->organization_id.'/profiles/'.$profile->id);
        $profile->save();

        return ApiResponse::data(['has_stamp' => true]);
    }

    public function stamp(CompanyProfile $profile, AuthorizationService $authorization, BrandingFileStore $files): StreamedResponse
    {
        $this->authorizeView($authorization);

        return $files->response($profile->stamp_path);
    }

    public function storeOffice(Request $request, CompanyProfile $profile, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::ORGANIZATION_MANAGE);
        $office = $profile->offices()->create($request->validate($this->officeRules()));

        return ApiResponse::data($office->only(['id', 'label', 'line_1', 'line_2', 'city', 'state', 'postal_code', 'country']), 201);
    }

    public function destroyOffice(OfficeAddress $office, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission(request()->user(), Permissions::ORGANIZATION_MANAGE);
        $office->delete();

        return ApiResponse::data(['deleted' => true]);
    }

    public function storeSignatory(Request $request, CompanyProfile $profile, AuthorizationService $authorization): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::ORGANIZATION_MANAGE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'designation' => ['required', 'string', 'max:120'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);
        if (($data['is_primary'] ?? false) === true) {
            $profile->signatories()->update(['is_primary' => false]);
        }
        $signatory = $profile->signatories()->create($data);

        return ApiResponse::data($this->signatoryPayload($signatory), 201);
    }

    public function uploadSignature(Request $request, AuthorizedSignatory $signatory, AuthorizationService $authorization, BrandingFileStore $files): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::BRANDING_MANAGE);
        $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $signatory->signature_path = $files->replace(
            $signatory->signature_path,
            $request->file('file'),
            'organizations/'.$signatory->organization_id.'/signatories/'.$signatory->id,
        );
        $signatory->save();

        return ApiResponse::data(['has_signature' => true]);
    }

    public function signature(AuthorizedSignatory $signatory, AuthorizationService $authorization, BrandingFileStore $files): StreamedResponse
    {
        $this->authorizeView($authorization);

        return $files->response($signatory->signature_path);
    }

    public function showIdentity(Brand $brand, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeView($authorization);
        $identity = BrandIdentity::query()->firstOrCreate(
            ['brand_id' => $brand->id],
            ['primary_color' => '#248396', 'secondary_color' => '#153E46'],
        );

        return ApiResponse::data($this->identityPayload($identity));
    }

    public function updateIdentity(Request $request, Brand $brand, AuthorizationService $authorization, AuditLogger $auditLogger, OrganizationContext $context): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::BRANDING_MANAGE);
        $data = $request->validate([
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'header_text' => ['nullable', 'string', 'max:180'],
            'footer_text' => ['nullable', 'string', 'max:180'],
            'organization_id' => ['prohibited'],
        ]);
        $identity = BrandIdentity::query()->firstOrCreate(['brand_id' => $brand->id]);
        $identity->fill($data);
        $identity->save();
        $auditLogger->accessChange($request->user(), $context->get(), 'brand_identity.saved', ['brand_id' => $brand->id]);

        return ApiResponse::data($this->identityPayload($identity));
    }

    public function uploadIdentityFile(Request $request, Brand $brand, AuthorizationService $authorization, BrandingFileStore $files): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::BRANDING_MANAGE);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['logo', 'alternate', 'light', 'dark', 'letterhead'])],
            'file' => ['required', 'file', 'max:4096', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof \Illuminate\Http\UploadedFile) {
                    $fail('Choose a PNG, JPG, or WebP logo.');

                    return;
                }
                $extension = strtolower($value->getClientOriginalExtension());
                $mime = strtolower((string) $value->getMimeType());
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                $mimes = ['image/jpeg', 'image/png', 'image/webp', 'application/octet-stream'];
                if (! in_array($extension, $allowed, true) || ! in_array($mime, $mimes, true)) {
                    $fail('Use a PNG, JPG, or WebP logo under 4 MB.');
                }
            }],
        ]);
        $identity = BrandIdentity::query()->firstOrCreate(['brand_id' => $brand->id]);
        $column = $this->assetColumn($data['kind']);
        $identity->{$column} = $files->replace(
            $identity->{$column},
            $request->file('file'),
            'organizations/'.$identity->organization_id.'/brands/'.$brand->id,
        );
        $identity->save();

        return ApiResponse::data($this->identityPayload($identity));
    }

    public function identityFile(Brand $brand, string $kind, AuthorizationService $authorization, BrandingFileStore $files): StreamedResponse
    {
        $this->authorizeView($authorization);
        $identity = BrandIdentity::query()->where('brand_id', $brand->id)->firstOrFail();

        return $files->response($identity->{$this->assetColumn($kind)});
    }

    private function authorizeView(AuthorizationService $authorization): void
    {
        $user = request()->user();
        foreach ([Permissions::DOCUMENTS_VIEW, Permissions::DOCUMENTS_GENERATE, Permissions::BRANDING_MANAGE, Permissions::ORGANIZATION_MANAGE] as $permission) {
            if ($authorization->hasPermission($user, $permission, app(OrganizationContext::class)->get())) {
                return;
            }
        }

        abort(403, 'You are not allowed to perform this action.');
    }

    /**
     * @return array<string, mixed>
     */
    private function profileRules(): array
    {
        return [
            'legal_company_name' => ['required', 'string', 'max:160'],
            'brand_name' => ['required', 'string', 'max:120'],
            'registered_address' => ['nullable', 'string', 'max:1000'],
            'cin' => ['nullable', 'regex:/^[A-Z0-9]{21}$/'],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'tan' => ['nullable', 'regex:/^[A-Z]{4}[0-9]{5}[A-Z]$/'],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:180'],
            'payroll_pf_number' => ['nullable', 'string', 'max:40'],
            'payroll_esi_number' => ['nullable', 'string', 'max:40'],
            'payroll_pt_number' => ['nullable', 'string', 'max:40'],
            'payroll_lin' => ['nullable', 'string', 'max:40'],
            'organization_id' => ['prohibited'],
            'legal_entity_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function officeRules(): array
    {
        return [
            'label' => ['required', 'string', 'max:80'],
            'line_1' => ['required', 'string', 'max:160'],
            'line_2' => ['nullable', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['required', 'string', 'max:80'],
            'postal_code' => ['required', 'string', 'max:12'],
            'country' => ['required', 'string', 'max:80'],
            'organization_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(CompanyProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'legal_entity_id' => $profile->legal_entity_id,
            'legal_company_name' => $profile->legal_company_name,
            'brand_name' => $profile->brand_name,
            'registered_address' => $profile->registered_address,
            'cin' => $profile->cin,
            'pan' => $profile->pan,
            'tan' => $profile->tan,
            'gstin' => $profile->gstin,
            'contact_email' => $profile->contact_email,
            'contact_phone' => $profile->contact_phone,
            'website' => $profile->website,
            'has_stamp' => $profile->stamp_path !== null,
            'payroll_pf_number' => $profile->payroll_pf_number,
            'payroll_esi_number' => $profile->payroll_esi_number,
            'payroll_pt_number' => $profile->payroll_pt_number,
            'payroll_lin' => $profile->payroll_lin,
            'offices' => $profile->offices->map(fn (OfficeAddress $office) => $office->only([
                'id', 'label', 'line_1', 'line_2', 'city', 'state', 'postal_code', 'country',
            ]))->values(),
            'signatories' => $profile->signatories->map(fn (AuthorizedSignatory $signatory) => $this->signatoryPayload($signatory))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function signatoryPayload(AuthorizedSignatory $signatory): array
    {
        return [
            'id' => $signatory->id,
            'name' => $signatory->name,
            'designation' => $signatory->designation,
            'is_primary' => $signatory->is_primary,
            'has_signature' => $signatory->signature_path !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function identityPayload(BrandIdentity $identity): array
    {
        return [
            'id' => $identity->id,
            'brand_id' => $identity->brand_id,
            'primary_color' => $identity->primary_color,
            'secondary_color' => $identity->secondary_color,
            'header_text' => $identity->header_text,
            'footer_text' => $identity->footer_text,
            'assets' => [
                'logo' => $identity->logo_path !== null,
                'alternate' => $identity->alternate_logo_path !== null,
                'light' => $identity->light_logo_path !== null,
                'dark' => $identity->dark_logo_path !== null,
                'letterhead' => $identity->letterhead_path !== null,
            ],
        ];
    }

    private function assetColumn(string $kind): string
    {
        return match ($kind) {
            'logo' => 'logo_path',
            'alternate' => 'alternate_logo_path',
            'light' => 'light_logo_path',
            'dark' => 'dark_logo_path',
            'letterhead' => 'letterhead_path',
            default => abort(404, 'The requested resource was not found.'),
        };
    }
}
