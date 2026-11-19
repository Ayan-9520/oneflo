<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issued_documents', function (Blueprint $table): void {
            $table->string('verification_hash', 64)->nullable()->unique('issued_doc_verify_hash');
        });

        Schema::create('document_signatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signatory_id')->constrained('authorized_signatories')->cascadeOnDelete();
            $table->string('content_sha256', 64);
            $table->timestamp('signed_at');
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('issued_document_id', 'doc_signature_document');
        });

        Schema::create('saved_report_filters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('report', 40);
            $table->json('filters');
            $table->timestamps();
            $table->unique(['organization_id', 'user_id', 'name'], 'saved_report_filter_name');
        });

        Schema::create('report_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report', 40);
            $table->json('filters');
            $table->string('format', 8);
            $table->string('cadence', 16);
            $table->date('next_run_on');
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('saved_report_filters');
        Schema::dropIfExists('document_signatures');
        Schema::table('issued_documents', function (Blueprint $table): void {
            $table->dropUnique('issued_doc_verify_hash');
            $table->dropColumn('verification_hash');
        });
    }
};
