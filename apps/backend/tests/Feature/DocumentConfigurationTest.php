<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\BrandIdentity;
use App\Models\CompanyProfile;
use App\Models\DocumentTemplateVersion;
use App\Models\LegalEntity;
use App\Models\Organization;
use App\Models\OrganizationAccess;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('documents');
    }

    public function test_preview_uses_the_employer_logo_and_keeps_oneflo_off_official_certificates(): void
    {
        [$organization, $admin] = $this->member('business-admin');
        $brandA = $this->brand($organization, 'North', 'north');
        $brandB = $this->brand($organization, 'South', 'south');
        $logoA = 'organizations/'.$organization->id.'/a.png';
        $logoB = 'organizations/'.$organization->id.'/b.png';
        Storage::disk('documents')->put($logoA, 'logo-north-bytes');
        Storage::disk('documents')->put($logoB, 'logo-south-bytes');
        BrandIdentity::unguarded(fn () => BrandIdentity::query()->create([
            'organization_id' => $organization->id,
            'brand_id' => $brandA->id,
            'logo_path' => $logoA,
        ]));
        BrandIdentity::unguarded(fn () => BrandIdentity::query()->create([
            'organization_id' => $organization->id,
            'brand_id' => $brandB->id,
            'logo_path' => $logoB,
        ]));
        $entity = $this->entity($organization, $brandA->id, 'North Private Limited');
        CompanyProfile::unguarded(fn () => CompanyProfile::query()->create([
            'organization_id' => $organization->id,
            'legal_entity_id' => $entity->id,
            'legal_company_name' => 'North Private Limited',
            'brand_name' => 'North',
        ]));

        $this->actingAs($admin);
        $official = $this->postJson('/api/v1/document-previews', $this->draft($entity->id, 'experience_certificate'), $this->header($organization))
            ->assertOk()
            ->json('data');
        $letter = $this->postJson('/api/v1/document-previews', $this->draft($entity->id, 'offer_letter'), $this->header($organization))
            ->assertOk()
            ->json('data');

        $this->assertStringContainsString(base64_encode('logo-north-bytes'), $official['html']);
        $this->assertStringNotContainsString(base64_encode('logo-south-bytes'), $official['html']);
        $this->assertStringContainsString('North Private Limited', $official['html']);
        $this->assertFalse($official['oneflo_attribution']);
        $this->assertStringNotContainsString('Powered by OneFlo', $official['html']);
        $this->assertTrue($letter['oneflo_attribution']);
        $this->assertStringContainsString('Powered by OneFlo', $letter['html']);
    }

    public function test_template_versions_are_preserved_and_other_organizations_stay_hidden(): void
    {
        [$organization, $admin] = $this->member('hr-admin');
        $entity = $this->entity($organization, null, 'Harbor Works');
        $other = Organization::factory()->create();
        $foreign = $this->entity($other, null, 'Other Works');

        $this->actingAs($admin);
        $this->postJson('/api/v1/document-templates', [
            ...$this->draft($entity->id, 'appointment_letter'),
            'body' => 'First {{company.legal_name}}',
        ], $this->header($organization))->assertCreated();

        $this->postJson('/api/v1/document-templates', [
            ...$this->draft($entity->id, 'appointment_letter'),
            'body' => 'Second {{company.legal_name}}',
        ], $this->header($organization))->assertCreated()
            ->assertJsonPath('data.current_version.version', 2);

        $this->assertSame('First {{company.legal_name}}', DocumentTemplateVersion::query()->where('version', 1)->value('body'));
        $this->assertSame('Second {{company.legal_name}}', DocumentTemplateVersion::query()->where('version', 2)->value('body'));

        $this->getJson('/api/v1/legal-entities/'.$foreign->id.'/company-profile', $this->header($organization))
            ->assertNotFound();
        $this->postJson('/api/v1/document-previews', $this->draft($foreign->id, 'offer_letter'), $this->header($organization))
            ->assertNotFound();
    }

    public function test_employees_cannot_save_templates_or_move_a_profile_between_organizations(): void
    {
        [$organization, $employee] = $this->member('employee');
        [$same, $admin] = $this->member('business-admin');
        $entity = $this->entity($same, null, 'Admin Entity');

        $this->actingAs($employee);
        $this->postJson('/api/v1/document-templates', $this->draft($entity->id, 'general_letter'), $this->header($organization))
            ->assertForbidden();

        $this->actingAs($admin);
        $this->putJson('/api/v1/legal-entities/'.$entity->id.'/company-profile', [
            'legal_company_name' => 'Admin Entity',
            'brand_name' => 'Admin',
            'organization_id' => $organization->id,
        ], $this->header($same))->assertUnprocessable();
        $this->assertDatabaseMissing('company_profiles', ['legal_company_name' => 'Admin Entity']);
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    private function member(string $roleSlug): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        OrganizationAccess::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => Role::query()->where('slug', $roleSlug)->firstOrFail()->id,
        ]);

        return [$organization, $user];
    }

    private function brand(Organization $organization, string $name, string $slug): Brand
    {
        return Brand::unguarded(fn () => Brand::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'slug' => $slug,
            'is_primary' => false,
        ]));
    }

    private function entity(Organization $organization, ?int $brandId, string $name): LegalEntity
    {
        return LegalEntity::unguarded(fn () => LegalEntity::query()->create([
            'organization_id' => $organization->id,
            'brand_id' => $brandId,
            'legal_name' => $name,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function draft(int $legalEntityId, string $type): array
    {
        return [
            'legal_entity_id' => $legalEntityId,
            'document_type' => $type,
            'name' => 'Standard '.$type,
            'body' => 'Issued for {{company.legal_name}}.',
            'show_logo' => true,
            'show_signatory' => false,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function header(Organization $organization): array
    {
        return ['X-Organization-Id' => (string) $organization->id];
    }
}
