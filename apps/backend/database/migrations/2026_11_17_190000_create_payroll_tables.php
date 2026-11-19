<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('kind');
            $table->boolean('taxable')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'payroll_component_code');
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('effective_on');
            $table->timestamps();
        });

        Schema::create('salary_structure_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('monthly_amount', 12, 2);
            $table->timestamps();
        });

        Schema::create('statutory_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->date('effective_on');
            $table->date('ended_on')->nullable();
            $table->json('config');
            $table->string('citation');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['legal_entity_id', 'code', 'effective_on'], 'statutory_rule_span');
        });

        Schema::create('employee_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
            $table->string('tax_regime')->default('new');
            $table->text('pan')->nullable();
            $table->timestamps();
            $table->unique('employee_id', 'employee_compensation_employee');
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status');
            $table->json('warnings');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->index(['legal_entity_id', 'year', 'month'], 'payroll_run_period');
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('lop_days')->default(0);
            $table->json('earnings');
            $table->json('deductions');
            $table->json('employer');
            $table->bigInteger('gross_paise');
            $table->bigInteger('deduction_paise');
            $table->bigInteger('net_paise');
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id'], 'payroll_line_employee');
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('component_code');
            $table->bigInteger('amount_paise');
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('last_working_on');
            $table->bigInteger('gratuity_paise');
            $table->bigInteger('leave_encashment_paise');
            $table->bigInteger('net_paise');
            $table->json('detail');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settlements');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_lines');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_compensations');
        Schema::dropIfExists('statutory_rules');
        Schema::dropIfExists('salary_structure_lines');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('payroll_components');
    }
};
