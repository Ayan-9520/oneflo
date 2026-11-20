<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_accounts', function (Blueprint $table): void {
            $table->string('password')->nullable()->after('phone_verified_at');
            $table->string('work_status', 20)->nullable()->after('headline');
            $table->boolean('marketing_opt_in')->default(false)->after('skills');
            $table->timestamp('terms_accepted_at')->nullable()->after('marketing_opt_in');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_accounts', function (Blueprint $table): void {
            $table->dropColumn(['password', 'work_status', 'marketing_opt_in', 'terms_accepted_at']);
        });
    }
};
