<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_requisitions', function (Blueprint $table): void {
            if (! Schema::hasColumn('job_requisitions', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('job_requisitions', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            }
        });

        Schema::table('job_offers', function (Blueprint $table): void {
            if (! Schema::hasColumn('job_offers', 'decision_note')) {
                $table->string('decision_note')->nullable()->after('status');
            }
            if (! Schema::hasColumn('job_offers', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('job_offers', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('sent_at');
            }
            if (! Schema::hasColumn('job_offers', 'declined_at')) {
                $table->timestamp('declined_at')->nullable()->after('accepted_at');
            }
        });

        Schema::table('legal_entities', function (Blueprint $table): void {
            if (! Schema::hasColumn('legal_entities', 'employee_code_prefix')) {
                $table->string('employee_code_prefix', 12)->nullable()->after('registration_number');
            }
            if (! Schema::hasColumn('legal_entities', 'employee_code_padding')) {
                $table->unsignedTinyInteger('employee_code_padding')->default(5)->after('employee_code_prefix');
            }
        });

        if (! Schema::hasTable('requisition_approvals')) {
            Schema::create('requisition_approvals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_requisition_id')->constrained()->cascadeOnDelete();
                $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('decision', 32);
                $table->text('comments')->nullable();
                $table->timestamp('acted_at');
                $table->timestamps();
                $table->index(['job_requisition_id', 'id'], 'requisition_approval_req');
            });
        }

        if (! Schema::hasTable('offer_approvals')) {
            Schema::create('offer_approvals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('job_offer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('decision', 32);
                $table->text('comments')->nullable();
                $table->timestamp('acted_at');
                $table->timestamps();
                $table->index(['job_offer_id', 'id'], 'offer_approval_offer');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_approvals');
        Schema::dropIfExists('requisition_approvals');
        Schema::table('legal_entities', function (Blueprint $table): void {
            $table->dropColumn(['employee_code_prefix', 'employee_code_padding']);
        });
        Schema::table('job_offers', function (Blueprint $table): void {
            $table->dropColumn(['decision_note', 'sent_at', 'accepted_at', 'declined_at']);
        });
        Schema::table('job_requisitions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn('submitted_at');
        });
    }
};
