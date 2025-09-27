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
        Schema::create('reconciliation_records', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('account_id');

            // period pivots
            $table->date('date');                 // transaction date (shown in UI)
            $table->string('batch_id')->nullable(); // optional import/run id

            // references to source rows (nullable when one-sided)
            $table->unsignedBigInteger('bank_id')->nullable();  // finance_bank_statements.id
            $table->unsignedBigInteger('app_id')->nullable();   // finance_account_entries.id

            // lightweight denormalized fields (useful for drill-down)
            $table->string('bank_reference')->nullable();
            $table->string('app_reference')->nullable();

            $table->decimal('bank_debit', 18, 2)->nullable();
            $table->decimal('bank_credit', 18, 2)->nullable();
            $table->decimal('app_debit', 18, 2)->nullable();
            $table->decimal('app_credit', 18, 2)->nullable();

            // classification (business labels)
            // discrepancy | dual_reflection | no_discrepancy
            $table->string('classification');

            // extra context
            $table->string('matched_by')->nullable();
            $table->text('context')->nullable();

            // bookkeeping
            $table->timestamps();

            // helpful indexes
            $table->index(['company_id', 'account_id', 'date']);
            $table->index(['company_id', 'account_id', 'batch_id']);
            // $table->index(['company_id', 'account_id', 'classification']);
            $table->index(['bank_id']);
            $table->index(['app_id']);

            // if you want to prevent duplicates per exact pair:
            $table->unique(['company_id', 'account_id', 'bank_id', 'app_id'], 'uniq_company_account_bank_app');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_records');
    }
};
