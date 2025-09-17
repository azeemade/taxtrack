<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reconciliation_records', function (Blueprint $table) {
            $table->unsignedBigInteger('run_id')->nullable()->after('account_id');
            $table->index(['company_id','run_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliation_records', function (Blueprint $table) {
            $table->dropIndex(['company_id','run_id']);
            $table->dropColumn('run_id');
        });
    }
};
