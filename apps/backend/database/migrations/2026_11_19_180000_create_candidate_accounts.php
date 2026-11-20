<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name', 160);
            $table->string('email', 160)->nullable()->unique('cand_acct_email');
            $table->string('phone', 20)->nullable()->unique('cand_acct_phone');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('google_id', 191)->nullable()->unique('cand_acct_google');
            $table->string('linkedin_id', 191)->nullable()->unique('cand_acct_linkedin');
            $table->string('avatar_url', 500)->nullable();
            $table->string('headline', 160)->nullable();
            $table->string('location', 120)->nullable();
            $table->unsignedTinyInteger('experience_years')->nullable();
            $table->string('current_company', 160)->nullable();
            $table->string('current_designation', 160)->nullable();
            $table->string('current_salary', 80)->nullable();
            $table->string('expected_salary', 80)->nullable();
            $table->string('notice_period', 80)->nullable();
            $table->string('qualification', 160)->nullable();
            $table->text('skills')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('candidate_login_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 10);
            $table->string('destination', 160);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['channel', 'destination'], 'cand_code_dest');
        });

        Schema::create('candidate_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_account_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique('cand_token_hash');
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('candidates', function (Blueprint $table): void {
            $table->foreignId('candidate_account_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('candidate_account_id');
        });
        Schema::dropIfExists('candidate_tokens');
        Schema::dropIfExists('candidate_login_codes');
        Schema::dropIfExists('candidate_accounts');
    }
};
