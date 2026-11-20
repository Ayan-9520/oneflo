<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_name');
            $table->string('status', 20)->default('previewed');
            $table->json('headers');
            $table->json('mapping');
            $table->longText('rows');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::table('candidates', function (Blueprint $table): void {
            $table->boolean('is_test_data')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('candidate_import_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['organization_id', 'is_test_data'], 'candidate_org_test_data');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropIndex('candidate_org_test_data');
            $table->dropConstrainedForeignId('candidate_import_id');
            $table->dropColumn(['is_test_data', 'notes']);
        });
        Schema::dropIfExists('candidate_imports');
    }
};
