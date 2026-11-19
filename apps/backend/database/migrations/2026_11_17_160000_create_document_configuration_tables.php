<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->constrained()->cascadeOnDelete();
            $table->string('legal_company_name');
            $table->string('brand_name');
            $table->text('registered_address')->nullable();
            $table->string('cin', 21)->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('tan', 10)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->string('stamp_path')->nullable();
            $table->string('payroll_pf_number')->nullable();
            $table->string('payroll_esi_number')->nullable();
            $table->string('payroll_pt_number')->nullable();
            $table->string('payroll_lin')->nullable();
            $table->timestamps();
            $table->unique('legal_entity_id');
            $table->index(['organization_id', 'legal_company_name']);
        });

        Schema::create('office_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_profile_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('line_1');
            $table->string('line_2')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('postal_code', 12);
            $table->string('country', 80);
            $table->timestamps();
            $table->index(['organization_id', 'company_profile_id']);
        });

        Schema::create('authorized_signatories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('designation');
            $table->string('signature_path')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index(['organization_id', 'company_profile_id']);
        });

        Schema::create('brand_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('logo_path')->nullable();
            $table->string('alternate_logo_path')->nullable();
            $table->string('light_logo_path')->nullable();
            $table->string('dark_logo_path')->nullable();
            $table->string('letterhead_path')->nullable();
            $table->string('primary_color', 7)->default('#248396');
            $table->string('secondary_color', 7)->default('#153E46');
            $table->string('header_text')->nullable();
            $table->string('footer_text')->nullable();
            $table->timestamps();
            $table->unique('brand_id');
        });

        Schema::create('platform_branding', function (Blueprint $table) {
            $table->id();
            $table->string('logo_path')->nullable();
            $table->string('powered_by_text');
            $table->string('website_url')->nullable();
            $table->boolean('show_footer')->default(true);
            $table->timestamps();
        });

        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('name');
            $table->string('scope_key');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
            $table->unique('scope_key');
            $table->index(['organization_id', 'document_type']);
        });

        Schema::create('document_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('body');
            $table->string('header_alignment')->default('left');
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_stamp')->default(false);
            $table->boolean('show_signatory')->default(true);
            $table->text('footer_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['document_template_id', 'version']);
        });

        Schema::table('document_templates', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('document_template_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('document_template_versions');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('platform_branding');
        Schema::dropIfExists('brand_identities');
        Schema::dropIfExists('authorized_signatories');
        Schema::dropIfExists('office_addresses');
        Schema::dropIfExists('company_profiles');
    }
};
