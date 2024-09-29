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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('vendor_id');
            $table->string('purchase_order_no');
            $table->unsignedBigInteger('invoice_id');
            $table->string('share_status')->comment('shared, not-shared');
            $table->date('purchase_order_date');
            $table->string('purchase_orderID');
            $table->decimal('purchase_order_value', 15, 2);
            $table->decimal('sub_total', 15, 2);
            $table->decimal('shipping_charge', 15, 2);
            $table->decimal('additional_charge', 15, 2);
            $table->decimal('discount', 15, 2);
            $table->decimal('vat', 15, 2);
            $table->decimal('tax', 15, 2);
            $table->string('status')->default('draft')->comment('draft, issued');
            $table->mediumText('terms_and_conditions')->nullable();
            $table->mediumText('additional_comment')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('company_id');
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
