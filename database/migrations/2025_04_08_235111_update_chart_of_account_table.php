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
        Schema::table('finance_chart_of_accounts', function (Blueprint $table) {
            // Remove any existing single-column unique index
            $table->dropUnique(['account_number']);

            // Add composite unique index
            $table->unique(
                ['account_number', 'company_id'],
                'finance_chart_of_accounts_account_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_chart_of_accounts', function (Blueprint $table) {
            // Remove the composite unique index
            $table->dropUnique('finance_chart_of_accounts_account_unique');

            // Restore the original single-column unique index
            $table->unique(['account_number']);
        });
    }
};
