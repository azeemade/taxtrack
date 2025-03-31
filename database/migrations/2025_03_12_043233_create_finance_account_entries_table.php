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
        Schema::create('finance_account_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->onDelete('cascade');
            $table->date('date');
            $table->foreignId('account_id')->constrained('finance_chart_of_accounts')->onDelete('cascade');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->decimal('debit_amount', 20, 2)->default(0);
            $table->decimal('credit_amount', 20, 2)->default(0);
            $table->decimal('amount', 20, 2);
            $table->date('transaction_date');
            $table->foreignId('edited_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_account_entries');
    }
};
