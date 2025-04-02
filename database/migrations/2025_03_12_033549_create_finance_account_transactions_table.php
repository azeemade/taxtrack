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
        Schema::create('finance_account_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('edited_by');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('trans_group_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->date('transaction_date');
            $table->string('transactionID')->nullable();
            $table->string('referenceID')->nullable();
            $table->string('mainBank')->nullable()->comment("true, false");

            $table->string('type')->nullable()->comment('expense', 'income');
            $table->string('payment_type')->nullable()->comment('payment', 'receipt');
            $table->string('category')->nullable()->comment('deposit', 'transfer', 'others');
            
            $table->double('amount', 15, 2)->nullable();
            $table->text('description')->nullable();
            $table->text('mode_of_payment')->nullable()->comment('cash', 'bank transfer', 'credit/debit card', 'cheque');
            $table->foreign('edited_by')->references('id')->on('users')->onDelete('cascade'); 
            $table->foreign('account_id')->references('id')->on('finance_chart_of_accounts')->onDelete('cascade'); 
            $table->foreign('trans_group_id')->references('id')->on('finance_account_transaction_groups')->onDelete('cascade'); 
            $table->foreign('journal_entry_id')->references('id')->on('finance_journal_entries')->onDelete('cascade'); 
            $table->timestamps();
            $table->softDeletes();


            // Indexes
            $table->index('transactionID');
            $table->index('referenceID');
            $table->index('account_id');
            $table->index('created_at');;
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_account_transactions');
    }
};
