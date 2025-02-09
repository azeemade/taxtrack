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
        Schema::create('document_default_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('bills_default_due_date_number')->nullable();
            $table->string('bills_default_due_date_condition')->nullable();
            $table->unsignedInteger('sales_invoice_default_due_date_number')->nullable();
            $table->string('sales_invoice_default_due_date_condition')->nullable();
            $table->string('invoice_prefix')->nullable();
            $table->string('invoice_prefix_number')->nullable();
            $table->string('credit_note_prefix')->nullable();
            $table->string('credit_note_prefix_number')->nullable();
            $table->string('debit_note_prefix')->nullable();
            $table->string('debit_note_prefix_number')->nullable();
            $table->string('purchase_order_prefix')->nullable();
            $table->string('purchase_order_prefix_number')->nullable();
            $table->string('quote_prefix')->nullable();
            $table->string('quote_prefix_number')->nullable();
            $table->string('receipt_prefix')->nullable();
            $table->string('receipt_prefix_number')->nullable();
            $table->unsignedInteger('quote_expiration_number')->nullable();
            $table->string('quote_expiration_condition')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('company_id');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_default_settings');
    }
};
