<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('department_categories')) {
        Schema::create('department_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('department_templates')) {
        Schema::create('department_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['department_category_id', 'name']);
        });
        }

        if (! Schema::hasTable('designation_templates')) {
        Schema::create('designation_templates', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('name');
            $table->timestamps();
            $table->unique(['category', 'name']);
        });
        }

        if (! Schema::hasTable('job_levels')) {
        Schema::create('job_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('rank')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });
        }

        if (! Schema::hasTable('job_grades')) {
        Schema::create('job_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });
        }

        if (! Schema::hasTable('employment_types')) {
        Schema::create('employment_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'code']);
        });
        }

        if (! Schema::hasColumn('departments', 'status')) {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('department_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->nullable();
            $table->foreignId('head_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unique(['organization_id', 'name']);
        });
        }

        if (! Schema::hasColumn('designations', 'status')) {
        Schema::table('designations', function (Blueprint $table) {
            $table->string('code')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_grade_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reports_to_designation_id')->nullable()->constrained('designations')->nullOnDelete();
            $table->foreignId('designation_template_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('min_experience_years')->nullable();
            $table->unsignedSmallInteger('max_experience_years')->nullable();
            $table->text('responsibilities')->nullable();
            $table->text('skills')->nullable();
            $table->string('salary_grade_code')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('archived_at')->nullable();
        });
        }

        if (! Schema::hasTable('organization_departments')) {
        Schema::create('organization_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->date('effective_on')->nullable();
            $table->timestamps();
            $table->unique('department_id');
        });
        }

        if (! Schema::hasTable('organization_designations')) {
        Schema::create('organization_designations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('designation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('designation_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->date('effective_on')->nullable();
            $table->timestamps();
            $table->unique('designation_id');
        });
        }

        if (! Schema::hasTable('designation_department_mappings')) {
        Schema::create('designation_department_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('designation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['designation_id', 'department_id'], 'designation_department_unique');
        });
        }

        if (! Schema::hasTable('employee_reporting_relationships')) {
        Schema::create('employee_reporting_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('effective_on');
            $table->date('ended_on')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'employee_id'], 'reporting_employee_index');
        });
        }

        if (! Schema::hasColumn('job_requisitions', 'department_id')) {
        Schema::table('job_requisitions', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
        });
        }
    }

    public function down(): void
    {
        Schema::table('job_requisitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('designation_id');
            $table->dropConstrainedForeignId('department_id');
        });
        Schema::dropIfExists('employee_reporting_relationships');
        Schema::dropIfExists('designation_department_mappings');
        Schema::dropIfExists('organization_designations');
        Schema::dropIfExists('organization_departments');
        Schema::table('designations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reports_to_designation_id');
            $table->dropConstrainedForeignId('designation_template_id');
            $table->dropConstrainedForeignId('employment_type_id');
            $table->dropConstrainedForeignId('job_grade_id');
            $table->dropConstrainedForeignId('job_level_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['code', 'description', 'min_experience_years', 'max_experience_years', 'responsibilities', 'skills', 'salary_grade_code', 'status', 'archived_at']);
        });
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'name']);
            $table->dropConstrainedForeignId('head_employee_id');
            $table->dropConstrainedForeignId('department_template_id');
            $table->dropConstrainedForeignId('department_category_id');
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['code', 'status', 'description', 'archived_at']);
        });
        Schema::dropIfExists('employment_types');
        Schema::dropIfExists('job_grades');
        Schema::dropIfExists('job_levels');
        Schema::dropIfExists('designation_templates');
        Schema::dropIfExists('department_templates');
        Schema::dropIfExists('department_categories');
    }
};
