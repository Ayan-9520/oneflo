<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Models\AuthorizedSignatory;
use App\Models\Brand;
use App\Models\BrandIdentity;
use App\Models\CompanyProfile;
use App\Models\LegalEntity;
use App\Models\PlatformBranding;
use App\Support\DocumentPlaceholders;
use App\Support\DocumentTypes;
use Illuminate\Support\Facades\View;

final class DocumentRenderer
{
    public function __construct(private readonly BrandingFileStore $files) {}

    /**
     * @param  array{
     *     body: string,
     *     header_alignment?: string,
     *     show_logo?: bool,
     *     show_stamp?: bool,
     *     show_signatory?: bool,
     *     footer_note?: string|null,
     *     signatory_id?: int|null
     * }  $draft
     * @return array{html: string, legal_entity_id: int, brand_id: int|null, official: bool, oneflo_attribution: bool}
     */
    /**
     * @param  array<string, string>  $placeholderValues
     */
    public function render(LegalEntity $legalEntity, string $documentType, array $draft, array $placeholderValues = []): array
    {
        if (! array_key_exists($documentType, DocumentTypes::catalog())) {
            abort(422, 'The document type is not recognized.');
        }

        $profile = CompanyProfile::query()->where('legal_entity_id', $legalEntity->id)->first();
        $brand = $this->brandFor($legalEntity);
        $identity = $brand === null ? null : BrandIdentity::query()->where('brand_id', $brand->id)->first();
        $platform = PlatformBranding::current();
        $official = DocumentTypes::isOfficial($documentType);
        $signatory = $this->signatory($profile, $draft['signatory_id'] ?? null);
        $showOneFlo = ! $official && $platform->show_footer;

        $values = [
            'company.legal_name' => $profile->legal_company_name ?? $legalEntity->legal_name,
            'company.brand_name' => $profile->brand_name ?? $brand?->name ?? '',
            'company.address' => $profile->registered_address ?? '',
            'company.cin' => $profile->cin ?? '',
            'company.pan' => $profile->pan ?? '',
            'company.tan' => $profile->tan ?? '',
            'company.gstin' => $profile->gstin ?? '',
            'company.email' => $profile->contact_email ?? '',
            'company.phone' => $profile->contact_phone ?? '',
            'company.website' => $profile->website ?? '',
            'employee.given_name' => 'Preview',
            'employee.family_name' => 'Employee',
            'employee.employee_code' => 'PREVIEW',
            'document.date' => now()->toDateString(),
            'document.number' => '',
            'signatory.name' => $signatory->name ?? '',
            'signatory.designation' => $signatory->designation ?? '',
        ];
        $values = array_merge($values, $placeholderValues);

        $body = DocumentPlaceholders::fill($draft['body'], $values);
        $logoPath = $identity?->logo_path;
        $primary = $this->hex($identity->primary_color ?? null, '#248396');
        $secondary = $this->hex($identity->secondary_color ?? null, '#153E46');

        $html = View::make('documents.preview', [
            'official' => $official,
            'title' => DocumentTypes::catalog()[$documentType]['label'],
            'legalName' => $values['company.legal_name'],
            'brandName' => $values['company.brand_name'],
            'address' => $values['company.address'],
            'email' => $values['company.email'],
            'phone' => $values['company.phone'],
            'website' => $values['company.website'],
            'cin' => $values['company.cin'],
            'pan' => $values['company.pan'],
            'tan' => $values['company.tan'],
            'gstin' => $values['company.gstin'],
            'headerText' => $official ? null : $identity?->header_text,
            'footerText' => $official ? null : $identity?->footer_text,
            'footerNote' => $draft['footer_note'] ?? null,
            'body' => $body,
            'alignment' => ($draft['header_alignment'] ?? 'left') === 'center' ? 'center' : 'left',
            'primaryColor' => $official ? '#172B35' : $primary,
            'secondaryColor' => $secondary,
            'logo' => ($draft['show_logo'] ?? true) ? $this->files->dataUri($logoPath) : null,
            'stamp' => ($draft['show_stamp'] ?? false) ? $this->files->dataUri($profile?->stamp_path) : null,
            'signature' => ($draft['show_signatory'] ?? true) ? $this->files->dataUri($signatory?->signature_path) : null,
            'signatoryName' => $signatory->name ?? null,
            'signatoryDesignation' => $signatory->designation ?? null,
            'showOneFlo' => $showOneFlo,
            'poweredBy' => $platform->powered_by_text,
            'platformWebsite' => $platform->website_url,
            'platformLogo' => $showOneFlo ? $this->files->dataUri($platform->logo_path) : null,
        ])->render();

        return [
            'html' => $html,
            'legal_entity_id' => $legalEntity->id,
            'brand_id' => $brand?->id,
            'official' => $official,
            'oneflo_attribution' => $showOneFlo,
        ];
    }

    private function brandFor(LegalEntity $legalEntity): ?Brand
    {
        if ($legalEntity->brand_id !== null) {
            return Brand::query()->find($legalEntity->brand_id);
        }

        return Brand::query()->where('is_primary', true)->orderBy('id')->first()
            ?? Brand::query()->orderBy('id')->first();
    }

    private function signatory(?CompanyProfile $profile, ?int $signatoryId): ?AuthorizedSignatory
    {
        if ($profile === null) {
            return null;
        }

        if ($signatoryId !== null) {
            return $profile->signatories()->whereKey($signatoryId)->first();
        }

        return $profile->signatories()->where('is_primary', true)->first()
            ?? $profile->signatories()->first();
    }

    private function hex(?string $color, string $fallback): string
    {
        return is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1 ? $color : $fallback;
    }
}
