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
        Schema::table('credit_note_invoices', function (Blueprint $table) {
            $table->boolean('credit_in_full')->default(false)->after('credit_amount_total');
            $table->string('reason', 100)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_note_invoices', function (Blueprint $table) {
            $table->dropColumn('credit_in_full');
            $table->dropColumn('reason');
        });
    }
};
