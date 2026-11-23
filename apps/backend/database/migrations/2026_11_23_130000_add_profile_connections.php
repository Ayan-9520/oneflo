<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_accounts', function (Blueprint $table): void {
            $table->string('looking_for', 160)->nullable()->after('headline');
        });

        Schema::create('profile_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_account_id')->constrained()->cascadeOnDelete();
            $table->string('from_name', 160);
            $table->string('from_email', 160);
            $table->string('role', 160);
            $table->string('message', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_connections');
        Schema::table('candidate_accounts', function (Blueprint $table): void {
            $table->dropColumn('looking_for');
        });
    }
};
