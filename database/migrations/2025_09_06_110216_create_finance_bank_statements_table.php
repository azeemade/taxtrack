<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_bank_statements', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date')->nullable();
            $table->string('referenceID')->nullable()->index();
            $table->text('description')->nullable();
            $table->date('value_date')->nullable();
            $table->decimal('withdrawals', 15, 2)->default(0);
            $table->decimal('lodgments', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('created_by')->index();
            $table->unsignedBigInteger('account_id')->index();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('finance_chart_of_accounts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_bank_statements');
    }
};
