<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->string('open_period', 7)->nullable();
        });

        $active = DB::table('payroll_runs')->where('status', '!=', 'reversed')->get(['id', 'legal_entity_id', 'year', 'month']);
        $seen = [];
        foreach ($active as $run) {
            $key = $run->legal_entity_id.'-'.$run->year.'-'.$run->month;
            if (isset($seen[$key])) {
                throw new \RuntimeException('Two active payroll runs share a legal employer and month.');
            }
            $seen[$key] = true;
            DB::table('payroll_runs')->where('id', $run->id)->update([
                'open_period' => sprintf('%04d-%02d', $run->year, $run->month),
            ]);
        }

        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->unique(['legal_entity_id', 'open_period'], 'payroll_run_open_period');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table): void {
            $table->dropUnique('payroll_run_open_period');
            $table->dropColumn('open_period');
        });
    }
};
