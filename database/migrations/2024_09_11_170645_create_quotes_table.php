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
        Schema::create('quotes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('quote_date');
            $table->string('additional_referenceID')->nullable();
            $table->string('quoteID');
            $table->mediumText('terms_and_conditions')->nullable();
            $table->mediumText('customer_note')->nullable();
            $table->decimal('sub_total', 15, 2)->default(0.00);
            $table->decimal('shipping_charge', 15, 2)->default(0.00);
            $table->decimal('additional_charge', 15, 2)->default(0.00);
            $table->string('status')->comment('pending, converted-to-invoice, draft');
            $table->string('share_status')->default('not-shared')->comment('shared, not-shared');
            $table->decimal('quote_total', 15, 2)->default(0.00);
            $table->string('description')->nullable();
            $table->mediumText('preview_link')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('currency_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('quotes')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
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
        Schema::dropIfExists('quotes');
    }
};
