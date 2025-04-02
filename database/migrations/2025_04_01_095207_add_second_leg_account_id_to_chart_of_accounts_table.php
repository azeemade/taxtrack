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
            $table->unsignedBigInteger('second_leg_account_id')->nullable();
            $table->foreign('second_leg_account_id')->references('id')->on('finance_chart_of_accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_chart_of_accounts', function (Blueprint $table) {
            $table->dropForeign(['second_leg_account_id']);
            $table->dropColumn('second_leg_account_id'); 
        });
        
    }
};
