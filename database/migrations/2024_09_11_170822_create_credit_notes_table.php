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
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('additional_referenceID')->nullable();
            $table->string('referenceID');
            $table->date('issue_date');
            $table->string('status')->comment('draft, issued');
            $table->string('share_status')->default('not-shared')->comment('shared, not-shared');
            $table->mediumText('preview_link')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('currency_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('credit_notes')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
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
        Schema::dropIfExists('credit_notes');
    }
};
