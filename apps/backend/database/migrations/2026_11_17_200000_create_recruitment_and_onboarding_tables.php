<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('job_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_requisition_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status');
            $table->date('opened_on');
            $table->timestamps();
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('given_name');
            $table->string('family_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('source')->nullable();
            $table->string('status');
            $table->timestamps();
            $table->index(['organization_id', 'status'], 'candidate_org_status');
        });

        Schema::create('candidate_resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_opening_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('stage');
            $table->timestamps();
            $table->unique(['job_opening_id', 'candidate_id'], 'application_opening_candidate');
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('interviewer');
            $table->string('mode');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('interview_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interview_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comments');
            $table->string('recommendation');
            $table->timestamps();
        });

        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->constrained()->cascadeOnDelete();
            $table->string('designation');
            $table->string('compensation_note');
            $table->string('status');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('onboarding_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('probation_ends_on')->nullable();
            $table->string('probation_status')->default('active');
            $table->string('identity_status')->default('pending');
            $table->timestamps();
            $table->unique('employee_id', 'onboarding_employee');
        });

        Schema::create('onboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('title');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'code'], 'onboarding_task_code');
        });

        Schema::create('employee_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('qualification');
            $table->string('institution');
            $table->unsignedSmallInteger('completed_year')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('ifsc', 11);
            $table->text('account_number');
            $table->timestamps();
        });

        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship');
            $table->string('phone', 30);
            $table->timestamps();
        });

        Schema::create('policy_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('policy_name');
            $table->date('accepted_on');
            $table->timestamps();
        });

        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('sensitive')->default(false);
            $table->boolean('requires_retention')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'document_category_code');
        });

        Schema::create('employee_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('label');
            $table->string('path');
            $table->boolean('sensitive')->default(false);
            $table->boolean('encrypted')->default(false);
            $table->string('reference_last4', 4)->nullable();
            $table->date('retention_until')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('series');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'series'], 'document_sequence_series');
        });

        Schema::create('issued_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type');
            $table->string('number');
            $table->unsignedSmallInteger('version');
            $table->date('issued_on');
            $table->longText('html');
            $table->json('lines');
            $table->boolean('oneflo_attribution')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'number', 'version'], 'issued_document_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issued_documents');
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('employee_files');
        Schema::dropIfExists('document_categories');
        Schema::dropIfExists('policy_acceptances');
        Schema::dropIfExists('emergency_contacts');
        Schema::dropIfExists('employee_bank_accounts');
        Schema::dropIfExists('employee_educations');
        Schema::dropIfExists('onboarding_tasks');
        Schema::dropIfExists('onboarding_profiles');
        Schema::dropIfExists('job_offers');
        Schema::dropIfExists('interview_feedback');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('candidate_resumes');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('job_openings');
        Schema::dropIfExists('job_requisitions');
    }
};
