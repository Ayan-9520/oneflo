<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique('subscription_plan_code');
            $table->string('name', 120);
            $table->string('interval', 16);
            $table->unsignedInteger('trial_days')->default(0);
            $table->unsignedBigInteger('price_paise')->nullable();
            $table->string('currency', 3)->default('INR');
            $table->unsignedInteger('employee_limit')->nullable();
            $table->json('features');
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status', 16);
            $table->date('trial_ends_on')->nullable();
            $table->date('current_period_start');
            $table->date('current_period_end');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique('organization_id', 'subscription_org');
        });

        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_paise');
            $table->string('currency', 3);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 16);
            $table->string('reference', 80)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('organization_id', 'billing_invoice_org');
        });

        Schema::create('organization_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique('organization_id', 'org_registration_org');
        });

        Schema::create('integration_providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 24);
            $table->string('endpoint');
            $table->text('secret');
            $table->boolean('enabled')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'kind'], 'int_provider_org_kind');
        });

        Schema::create('integration_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 24);
            $table->string('status', 24);
            $table->string('detail')->nullable();
            $table->string('subject', 80);
            $table->timestamps();
            $table->index(['organization_id', 'kind'], 'int_delivery_org_kind');
        });

        Schema::create('ai_providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint');
            $table->text('secret');
            $table->boolean('enabled')->default(false);
            $table->timestamps();
            $table->unique('organization_id', 'ai_provider_org');
        });

        Schema::create('workflow_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('event', 40);
            $table->json('steps');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'event'], 'workflow_org_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_definitions');
        Schema::dropIfExists('ai_providers');
        Schema::dropIfExists('integration_deliveries');
        Schema::dropIfExists('integration_providers');
        Schema::dropIfExists('organization_registrations');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
