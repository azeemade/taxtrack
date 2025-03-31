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
        DB::statement('SET FOREIGN_KEY_CHECKS=0;'); // Disable foreign key checks

        Schema::create('credit_note_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->decimal('credit_amount_total', 15, 2)->default(0.00);
            $table->string('status')->default('added')->comment('added, removed');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('credit_note_id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('line_item_id');

            // Foreign Keys
            $table->foreign('credit_note_id')->references('id')->on('credit_notes')->onDelete('cascade');
            $table->foreign('line_item_id')->references('id')->on('line_items')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->timestamps();
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1;'); // Re-enable foreign key checks
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_note_invoices');
    }
};
