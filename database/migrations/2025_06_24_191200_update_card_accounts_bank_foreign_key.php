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
        Schema::table('card_accounts', function (Blueprint $table) {
            $table->dropForeign(['issuing_bank_id']);
            
            // Add the new foreign key constraint to finance_chart_of_accounts
            $table->foreign('issuing_bank_id')
                  ->references('id')
                  ->on('finance_chart_of_accounts')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_accounts', function (Blueprint $table) {
            $table->dropForeign(['issuing_bank_id']);
            
            // Restore the original foreign key constraint to banks
            $table->foreign('issuing_bank_id')
                  ->references('id')
                  ->on('banks')
                  ->onDelete('set null');
        });
    }
};
