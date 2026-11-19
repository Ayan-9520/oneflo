<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->json('approval_steps');
            $table->boolean('location_tracking_enabled')->default(false);
            $table->timestamps();
            $table->unique('organization_id', 'attendance_settings_org');
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->unsignedSmallInteger('early_grace_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_threshold_minutes')->default(0);
            $table->json('weekly_offs');
            $table->timestamps();
        });

        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->date('effective_on');
            $table->date('ended_on')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'effective_on'], 'shift_assignment_span');
        });

        Schema::create('shift_rotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('shift_rotation_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_rotation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_count');
            $table->timestamps();
        });

        Schema::create('shift_rotation_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_rotation_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ended_on')->nullable();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_entity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->date('observed_on');
            $table->timestamps();
            $table->index(['organization_id', 'observed_on'], 'holidays_org_date');
        });

        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->dateTime('punched_at');
            $table->string('kind');
            $table->string('work_mode');
            $table->timestamps();
            $table->index(['employee_id', 'work_date'], 'attendance_punch_day');
        });

        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->string('work_mode')->nullable();
            $table->string('status');
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('scheduled_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'employee_id', 'work_date'], 'attendance_day_unique');
        });

        Schema::create('attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->string('type');
            $table->time('requested_in')->nullable();
            $table->time('requested_out')->nullable();
            $table->string('reason')->nullable();
            $table->string('status');
            $table->string('current_step')->nullable();
            $table->json('approval_steps');
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->boolean('paid')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'leave_type_code');
        });

        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('annual_days', 6, 2);
            $table->decimal('accrual_days', 6, 2)->default(0);
            $table->decimal('carry_forward_max', 6, 2)->default(0);
            $table->boolean('encashment_allowed')->default(false);
            $table->decimal('encashment_max', 6, 2)->default(0);
            $table->json('approval_steps');
            $table->timestamps();
            $table->unique(['organization_id', 'leave_type_id', 'year'], 'leave_policy_year');
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('opening_days', 6, 2)->default(0);
            $table->decimal('accrued_days', 6, 2)->default(0);
            $table->decimal('used_days', 6, 2)->default(0);
            $table->decimal('carried_days', 6, 2)->default(0);
            $table->decimal('encashed_days', 6, 2)->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'leave_type_id', 'year'], 'leave_balance_year');
        });

        Schema::create('leave_accrual_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('days', 6, 2);
            $table->timestamps();
            $table->unique(['employee_id', 'leave_type_id', 'year', 'month'], 'leave_accrual_month');
        });

        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('days', 6, 2);
            $table->string('reason')->nullable();
            $table->string('status');
            $table->string('current_step')->nullable();
            $table->json('approval_steps');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
        Schema::dropIfExists('leave_accrual_entries');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendance_requests');
        Schema::dropIfExists('attendance_days');
        Schema::dropIfExists('attendance_punches');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('shift_rotation_assignments');
        Schema::dropIfExists('shift_rotation_slots');
        Schema::dropIfExists('shift_rotations');
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('attendance_settings');
    }
};
