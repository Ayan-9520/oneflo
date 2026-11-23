<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('follower_account_id')->constrained('candidate_accounts')->cascadeOnDelete();
            $table->foreignId('following_account_id')->constrained('candidate_accounts')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['follower_account_id', 'following_account_id'], 'profile_follow_pair');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_follows');
    }
};
