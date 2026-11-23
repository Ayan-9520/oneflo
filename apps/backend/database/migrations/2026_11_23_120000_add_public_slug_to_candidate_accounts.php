<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_accounts', function (Blueprint $table): void {
            $table->string('public_slug', 80)->nullable()->unique('cand_acct_public_slug');
            $table->boolean('is_public')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('candidate_accounts', function (Blueprint $table): void {
            $table->dropUnique('cand_acct_public_slug');
            $table->dropColumn(['public_slug', 'is_public']);
        });
    }
};
