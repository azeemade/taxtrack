<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        DB::statement('SET FOREIGN_KEY_CHECKS=0;'); // Disable foreign key checks
        
        Schema::table('finance_chart_of_accounts', function (Blueprint $table) {
            $table->string('holder_name')->nullable();
            $table->string('account_type')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->foreign('bank_id')->references('id')->on('banks')->onDelete('set null');

            DB::statement('SET FOREIGN_KEY_CHECKS=1;'); // Re-enable foreign key checks
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);

            $table->dropColumn([
                'holder_name',
                'account_type',
                'bank_id',
            ]);
        });
    }
};
