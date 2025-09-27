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
        Schema::table('finance_account_transactions', function (Blueprint $table) {
            $table->double('bank_fee', 15, 2)
                  ->nullable()
                  ->default(0.00)
                  ->after('amount');
                  
            $table->double('exchange_rate', 15, 2)
                  ->nullable()
                  ->default(0.00)
                  ->after('bank_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_account_transactions', function (Blueprint $table) {
            $table->dropColumn(['bank_fee', 'exchange_rate']);
        });
    }
};