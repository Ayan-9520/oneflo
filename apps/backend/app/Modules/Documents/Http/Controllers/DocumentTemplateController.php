<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\LegalEntity;
use App\Models\PlatformBranding;
use App\Modules\Documents\Services\BrandingFileStore;
use App\Modules\Documents\Services\DocumentRenderer;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\OrganizationContext;
use App\Support\ApiResponse;
use App\Support\DocumentPlaceholders;
use App\Support\DocumentTypes;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentTemplateController extends Controller
{
    public function types(AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeView($authorization);
        $types = [];
        foreach (DocumentTypes::catalog() as $key => $definition) {
            $types[] = ['key' => $key, 'label' => $definition['label'], 'official' => $definition['official']];
        }

        return ApiResponse::data([
            'types' => $types,
            'placeholders' => DocumentPlaceholders::descriptions(),
        ]);
    }

    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeView($authorization);
        $templates = DocumentTemplate::query()
            ->with('currentVersion')
            ->when($request->filled('legal_entity_id'), fn ($query) => $query->where('legal_entity_id', $request->integer('legal_entity_id')))
            ->orderBy('document_type')
            ->get();

        return ApiResponse::data($templates->map(fn (DocumentTemplate $template) => $this->payload($template))->all());
    }

    public function store(Request $request, AuthorizationService $authorization, AuditLogger $auditLogger, OrganizationContext $context): JsonResponse
    {
        $authorization->authorizePermission($request->user(), Permissions::DOCUMENTS_GENERATE);
        $data = $this->validatedDraft($request);
        $legalEntityId = $data['legal_entity_id'];
        if ($legalEntityId !== null) {
            LegalEntity::query()->findOrFail($legalEntityId);
        }

        $template = DB::transaction(function () use ($request, $context, $data, $legalEntityId): DocumentTemplate {
            $template = DocumentTemplate::query()->firstOrCreate(
                ['scope_key' => DocumentTemplate::scopeKey((int) $context->id(), $legalEntityId, $data['document_type'])],
                [
                    'legal_entity_id' => $legalEntityId,
                    'document_type' => $data['document_type'],
                    'name' => $data['name'],
                ],
            );
            $template->name = $data['name'];
            $versionNumber = ((int) $template->versions()->max('version')) + 1;
            $version = $template->versions()->create([
                'version' => $versionNumber,
                'body' => $data['body'],
                'header_alignment' => $data['header_alignment'],
                'show_logo' => $data['show_logo'],
                'show_stamp' => $data['show_stamp'],
                'show_signatory' => $data['show_signatory'],
                'footer_note' => $data['footer_note'],
                'created_by' => $request->user()->id,
            ]);
            $template->current_version_id = $version->id;
            $template->save();

            return $template->load('currentVersion');
        });

        $auditLogger->accessChange($request->user(), $context->get(), 'document_template.versioned', [
            'document_template_id' => $template->id,
            'version' => $template->currentVersion?->version,
            'document_type' => $template->document_type,
        ]);

        return ApiResponse::data($this->payload($template), 201);
    }

    public function preview(Request $request, AuthorizationService $authorization, DocumentRenderer $renderer): JsonResponse
    {
        $user = $request->user();
        $organization = app(OrganizationContext::class)->get();
        if (! $authorization->hasPermission($user, Permissions::DOCUMENTS_VIEW, $organization)
            && ! $authorization->hasPermission($user, Permissions::DOCUMENTS_GENERATE, $organization)) {
            abort(403, 'You are not allowed to perform this action.');
        }

        $data = $this->validatedDraft($request);
        $legalEntity = LegalEntity::query()->findOrFail($data['legal_entity_id']);
        $rendered = $renderer->render($legalEntity, $data['document_type'], $data);

        return ApiResponse::data($rendered);
    }

    public function platformBranding(AuthorizationService $authorization): JsonResponse
    {
        $this->authorizeView($authorization);

        return ApiResponse::data($this->platformPayload(PlatformBranding::current()));
    }

    public function updatePlatformBranding(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $allowed = $request->user()->platformRoles()
            ->whereHas('permissions', fn ($query) => $query->where('name', Permissions::BRANDING_MANAGE))
            ->exists();
        if (! $allowed) {
            abort(403, 'You are not allowed to perform this action.');
        }

        $data = $request->validate([
            'powered_by_text' => ['required', 'string', 'max:80'],
            'website_url' => ['nullable', 'url', 'max:180'],
            'show_footer' => ['required', 'boolean'],
        ]);
        $branding = PlatformBranding::current();
        $branding->fill($data);
        $branding->save();
        $auditLogger->platform($request->user(), 'platform_branding.saved', ['show_footer' => $branding->show_footer]);

        return ApiResponse::data($this->platformPayload($branding));
    }

    public function uploadPlatformLogo(Request $request, BrandingFileStore $files): JsonResponse
    {
        $allowed = $request->user()->platformRoles()
            ->whereHas('permissions', fn ($query) => $query->where('name', Permissions::BRANDING_MANAGE))
            ->exists();
        if (! $allowed) {
            abort(403, 'You are not allowed to perform this action.');
        }
        $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $branding = PlatformBranding::current();
        $branding->logo_path = $files->replace($branding->logo_path, $request->file('file'), 'platform');
        $branding->save();

        return ApiResponse::data(['has_logo' => true]);
    }

    public function platformLogo(BrandingFileStore $files): StreamedResponse
    {
        return $files->response(PlatformBranding::current()->logo_path);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedDraft(Request $request): array
    {
        $data = $request->validate([
            'legal_entity_id' => ['required', 'integer'],
            'document_type' => ['required', 'in:'.implode(',', DocumentTypes::keys())],
            'name' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:20000'],
            'header_alignment' => ['sometimes', 'in:left,center'],
            'show_logo' => ['sometimes', 'boolean'],
            'show_stamp' => ['sometimes', 'boolean'],
            'show_signatory' => ['sometimes', 'boolean'],
            'footer_note' => ['nullable', 'string', 'max:500'],
            'signatory_id' => ['nullable', 'integer'],
            'organization_id' => ['prohibited'],
        ]);
        DocumentPlaceholders::assertKnown($data['body']);

        return [
            'legal_entity_id' => (int) $data['legal_entity_id'],
            'document_type' => $data['document_type'],
            'name' => $data['name'],
            'body' => $data['body'],
            'header_alignment' => $data['header_alignment'] ?? 'left',
            'show_logo' => $request->boolean('show_logo', true),
            'show_stamp' => $request->boolean('show_stamp'),
            'show_signatory' => $request->boolean('show_signatory', true),
            'footer_note' => $data['footer_note'] ?? null,
            'signatory_id' => isset($data['signatory_id']) ? (int) $data['signatory_id'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(DocumentTemplate $template): array
    {
        $version = $template->currentVersion;

        return [
            'id' => $template->id,
            'legal_entity_id' => $template->legal_entity_id,
            'document_type' => $template->document_type,
            'name' => $template->name,
            'current_version' => $version === null ? null : [
                'id' => $version->id,
                'version' => $version->version,
                'body' => $version->body,
                'header_alignment' => $version->header_alignment,
                'show_logo' => $version->show_logo,
                'show_stamp' => $version->show_stamp,
                'show_signatory' => $version->show_signatory,
                'footer_note' => $version->footer_note,
                'created_at' => $version->created_at,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function platformPayload(PlatformBranding $branding): array
    {
        return [
            'powered_by_text' => $branding->powered_by_text,
            'website_url' => $branding->website_url,
            'show_footer' => $branding->show_footer,
            'has_logo' => $branding->logo_path !== null,
        ];
    }

    private function authorizeView(AuthorizationService $authorization): void
    {
        $user = request()->user();
        $organization = app(OrganizationContext::class)->get();
        foreach ([Permissions::DOCUMENTS_VIEW, Permissions::DOCUMENTS_GENERATE, Permissions::BRANDING_MANAGE] as $permission) {
            if ($authorization->hasPermission($user, $permission, $organization)) {
                return;
            }
        }

        abort(403, 'You are not allowed to perform this action.');
    }
}
