<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('endpoint');
            $table->string('sender')->nullable();
            $table->text('secret');
            $table->boolean('enabled')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'channel'], 'notif_provider_channel');
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 40);
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(true);
            $table->boolean('sms')->default(false);
            $table->boolean('whatsapp')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'user_id', 'event'], 'notif_pref_user_event');
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 40);
            $table->string('dedupe_key', 160);
            $table->string('title');
            $table->text('body');
            $table->boolean('in_app')->default(true);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id', 'dedupe_key'], 'notif_org_user_dedupe');
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->string('detail')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_providers');
    }
};
