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
        Schema::table('line_items', function (Blueprint $table) {
            if (!Schema::hasColumn('line_items', 'account_id')) {
                $table->unsignedBigInteger('account_id')->after('item_details')->nullable();
                $table->foreign('account_id')
                      ->references('id')
                      ->on('finance_chart_of_accounts')
                      ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('line_items', function (Blueprint $table) {
            if (Schema::hasColumn('line_items', 'account_id')) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            }
        });
    }
};
