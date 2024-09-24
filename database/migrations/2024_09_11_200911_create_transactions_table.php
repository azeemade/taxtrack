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
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('transactionID')->nullable();
            $table->string('referenceID');
            $table->string('description')->nullable();
            $table->date('transaction_date')->nullable();
            $table->decimal('transaction_value', 15, 2)->default(0.00);
            $table->decimal('inflow_amount', 15, 2)->default(0.00);
            $table->unsignedInteger('inflow_number')->default(1);
            $table->decimal('outflow_amount', 15, 2)->default(0.00);
            $table->unsignedInteger('outflow_number')->default(1);
            $table->string('status')->default('draft')->comment('posted, draft');
            $table->string('entry_type')->comment('debit, credit');
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('company_id');
            // $table->foreign('account_id')->references('id')->on('categories')->onDelete('set null');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('set null');
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
        Schema::dropIfExists('transactions');
    }
};
