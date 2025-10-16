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
             // Quantities & costing for inventory returns
             $table->decimal('quantity_returned', 18, 4)->nullable()->after('credit_amount_total');
             $table->enum('condition', ['good', 'damaged'])->default('good')->after('quantity_returned');
             $table->decimal('override_cost', 18, 4)->nullable()->after('condition');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_note_invoices', function (Blueprint $table) {
             $table->dropColumn(['quantity_returned', 'condition', 'override_cost']);
        });
    }
};
