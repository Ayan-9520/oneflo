<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'person_key')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->uuid('person_key')->nullable()->index();
                $table->string('email')->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('photo_path')->nullable();
                $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('legal_entity_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->string('employment_type')->default('permanent');
                $table->date('joined_on')->nullable();
                $table->string('work_location')->nullable();
            });
        }

        foreach (DB::table('employees')->whereNull('person_key')->pluck('id') as $id) {
            DB::table('employees')->where('id', $id)->update(['person_key' => (string) Str::uuid()]);
        }

        Schema::dropIfExists('employment_records');

        Schema::create('employment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->uuid('person_key')->index();
            $table->foreignId('legal_entity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('manager_employee_id')->nullable();
            $table->string('employment_type');
            $table->string('work_location')->nullable();
            $table->string('status');
            $table->string('change_type');
            $table->date('effective_on');
            $table->date('ended_on')->nullable();
            $table->date('last_working_on')->nullable();
            $table->string('reason')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'employee_id', 'effective_on'], 'employment_org_employee_effective');
            $table->index(['person_key', 'effective_on'], 'employment_person_effective');
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('leader_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['team_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('employment_records');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('brand_id');
            $table->dropConstrainedForeignId('legal_entity_id');
            $table->dropColumn(['person_key', 'email', 'phone', 'photo_path', 'employment_type', 'joined_on', 'work_location']);
        });
    }
};
