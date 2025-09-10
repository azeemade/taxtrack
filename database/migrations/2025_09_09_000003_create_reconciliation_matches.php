<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reconciliation_matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('finance_bank_statement_id')->index();
            $table->unsignedBigInteger('finance_account_entry_id')->index();
            $table->decimal('matched_amount', 18, 2);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('finance_bank_statement_id')->references('id')->on('finance_bank_statements')->onDelete('cascade');
            $table->foreign('finance_account_entry_id')->references('id')->on('finance_account_entries')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('reconciliation_matches');
    }
};