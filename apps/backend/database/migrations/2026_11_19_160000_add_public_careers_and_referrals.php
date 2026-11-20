<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_openings', function (Blueprint $table): void {
            if (! Schema::hasColumn('job_openings', 'slug')) {
                $table->string('slug')->nullable()->unique('job_opening_slug');
            }
            if (! Schema::hasColumn('job_openings', 'is_public')) {
                $table->boolean('is_public')->default(false);
            }
            if (! Schema::hasColumn('job_openings', 'location')) {
                $table->string('location')->nullable();
            }
            if (! Schema::hasColumn('job_openings', 'employment_type')) {
                $table->string('employment_type', 40)->nullable();
            }
            if (! Schema::hasColumn('job_openings', 'category')) {
                $table->string('category', 80)->nullable();
            }
            if (! Schema::hasColumn('job_openings', 'experience_min')) {
                $table->unsignedTinyInteger('experience_min')->nullable();
            }
            if (! Schema::hasColumn('job_openings', 'experience_max')) {
                $table->unsignedTinyInteger('experience_max')->nullable();
            }
            if (! Schema::hasColumn('job_openings', 'salary_public')) {
                $table->boolean('salary_public')->default(false);
            }
            if (! Schema::hasColumn('job_openings', 'salary_note')) {
                $table->string('salary_note')->nullable();
            }
            if (! Schema::hasColumn('job_openings', 'show_headcount')) {
                $table->boolean('show_headcount')->default(false);
            }
            if (! Schema::hasColumn('job_openings', 'responsibilities')) {
                $table->text('responsibilities')->nullable();
            }
            if (! Schema::hasColumn('job_openings', 'qualifications')) {
                $table->text('qualifications')->nullable();
            }
            if (! Schema::hasColumn('job_openings', 'skills')) {
                $table->text('skills')->nullable();
            }
            if (! Schema::hasColumn('job_openings', 'benefits')) {
                $table->text('benefits')->nullable();
            }
            if (! Schema::hasColumn('job_openings', 'deadline_on')) {
                $table->date('deadline_on')->nullable();
            }
            if (! Schema::hasColumn('job_openings', 'published_at')) {
                $table->timestamp('published_at')->nullable();
            }
        });

        Schema::table('candidates', function (Blueprint $table): void {
            foreach ([
                'location' => fn (Blueprint $table) => $table->string('location')->nullable(),
                'experience_years' => fn (Blueprint $table) => $table->unsignedTinyInteger('experience_years')->nullable(),
                'current_company' => fn (Blueprint $table) => $table->string('current_company')->nullable(),
                'current_designation' => fn (Blueprint $table) => $table->string('current_designation')->nullable(),
                'notice_period' => fn (Blueprint $table) => $table->string('notice_period', 80)->nullable(),
                'qualification' => fn (Blueprint $table) => $table->string('qualification')->nullable(),
                'skills' => fn (Blueprint $table) => $table->text('skills')->nullable(),
            ] as $column => $define) {
                if (! Schema::hasColumn('candidates', $column)) {
                    $define($table);
                }
            }
        });

        Schema::table('job_applications', function (Blueprint $table): void {
            foreach (['source', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content'] as $column) {
                if (! Schema::hasColumn('job_applications', $column)) {
                    $table->string($column, 80)->nullable();
                }
            }
            if (! Schema::hasColumn('job_applications', 'message')) {
                $table->text('message')->nullable();
            }
            if (! Schema::hasColumn('job_applications', 'current_salary')) {
                $table->string('current_salary', 80)->nullable();
            }
            if (! Schema::hasColumn('job_applications', 'expected_salary')) {
                $table->string('expected_salary', 80)->nullable();
            }
            if (! Schema::hasColumn('job_applications', 'consented_at')) {
                $table->timestamp('consented_at')->nullable();
            }
            if (! Schema::hasColumn('job_applications', 'privacy_version')) {
                $table->string('privacy_version', 40)->nullable();
            }
            if (! Schema::hasColumn('job_applications', 'acknowledgement_status')) {
                $table->string('acknowledgement_status', 20)->default('not_sent');
            }
        });

        if (! Schema::hasTable('employee_referrals')) {
            Schema::create('employee_referrals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_opening_id')->constrained()->cascadeOnDelete();
                $table->string('token', 64)->unique('referral_token');
                $table->string('status', 40)->default('submitted');
                $table->string('note')->nullable();
                $table->foreignId('candidate_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('job_application_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->unique(['employee_id', 'job_opening_id'], 'referral_employee_opening');
            });
        }

        if (! Schema::hasTable('referral_reward_policies')) {
            Schema::create('referral_reward_policies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_opening_id')->nullable()->constrained()->nullOnDelete();
                $table->boolean('enabled')->default(false);
                $table->unsignedInteger('amount')->default(0);
                $table->string('milestone', 40)->default('joined');
                $table->timestamps();
                $table->unique(['organization_id', 'job_opening_id'], 'referral_policy_opening');
            });
        }

        if (! Schema::hasTable('referral_rewards')) {
            Schema::create('referral_rewards', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_referral_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('amount')->default(0);
                $table->string('status', 32)->default('not_eligible');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->string('payout_status', 32)->default('unpaid');
                $table->string('payout_reference')->nullable();
                $table->string('notes')->nullable();
                $table->timestamps();
                $table->unique('employee_referral_id', 'referral_reward_once');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
        Schema::dropIfExists('referral_reward_policies');
        Schema::dropIfExists('employee_referrals');
    }
};
