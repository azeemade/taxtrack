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
        Schema::create('line_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('documentable_id');
            $table->string('documentable_type'); //invoice, quote, purchase_order, purchase_invoice
            $table->string('item_details')->nullable();
            $table->unsignedBigInteger('quantity')->default(0);
            $table->decimal('price', 15, 2)->default(0.00);
            $table->decimal('discount', 15, 2)->default(0.00);
            $table->decimal('vat', 15, 2)->default(0.00);
            $table->decimal('credit_amount', 15, 2)->default(0.00);
            $table->boolean('full_credit')->default(false);
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('debit_note_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->foreign('debit_note_id')->references('id')->on('debit_notes')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
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
        Schema::dropIfExists('line_items');
    }
};
