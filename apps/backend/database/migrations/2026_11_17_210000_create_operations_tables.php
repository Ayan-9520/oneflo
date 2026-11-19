<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable();
        });

        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit');
            $table->unsignedTinyInteger('weight')->default(1);
            $table->timestamps();
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('scope');
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('kpi_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('period');
            $table->date('period_on');
            $table->string('target');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('cycle');
            $table->date('period_on');
            $table->text('self_comments')->nullable();
            $table->unsignedTinyInteger('self_score')->nullable();
            $table->text('manager_comments')->nullable();
            $table->unsignedTinyInteger('manager_score')->nullable();
            $table->boolean('promotion_recommended')->default(false);
            $table->string('status');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'period_on'], 'review_employee_period');
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('asset_code');
            $table->string('category');
            $table->string('name');
            $table->string('condition');
            $table->string('status');
            $table->timestamps();
            $table->unique(['organization_id', 'asset_code'], 'asset_org_code');
        });

        Schema::create('asset_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('allocated_on');
            $table->date('returned_on')->nullable();
            $table->string('return_condition')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->date('performed_on');
            $table->string('notes');
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['organization_id', 'name'], 'expense_category_name');
        });

        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('incurred_on');
            $table->string('description');
            $table->string('status');
            $table->string('receipt_path')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->foreignId('manager_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finance_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('priority');
            $table->unsignedSmallInteger('response_hours');
            $table->timestamps();
            $table->unique(['organization_id', 'priority'], 'sla_org_priority');
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_category_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->string('priority');
            $table->string('status');
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('title');
            $table->text('body');
            $table->date('occurs_on')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('anonymous')->default(false);
            $table->timestamps();
        });

        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('question');
            $table->date('closes_on')->nullable();
            $table->timestamps();
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->text('answer');
            $table->timestamps();
            $table->unique(['survey_id', 'employee_id'], 'survey_response_employee');
        });

        Schema::create('recognitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('message');
            $table->date('awarded_on');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recognitions');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('surveys');
        Schema::dropIfExists('employee_feedback');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('sla_policies');
        Schema::dropIfExists('ticket_categories');
        Schema::dropIfExists('expense_claims');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('asset_maintenances');
        Schema::dropIfExists('asset_allocations');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('goals');
        Schema::dropIfExists('kpis');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('date_of_birth');
        });
    }
};
