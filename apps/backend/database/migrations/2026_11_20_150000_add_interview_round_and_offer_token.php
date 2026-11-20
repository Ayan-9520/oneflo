<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            $table->string('round')->default('interview')->after('mode');
        });
        Schema::table('job_offers', function (Blueprint $table) {
            $table->string('response_token', 64)->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('interviews', function (Blueprint $table) {
            $table->dropColumn('round');
        });
        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropColumn('response_token');
        });
    }
};
