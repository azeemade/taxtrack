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
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('account_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('batch_id')->nullable(); // optional import id
            $table->string('title')->nullable();    // optional label for UI
            $table->unsignedInteger('discrepancies')->default(0);
            $table->unsignedInteger('dual_reflections')->default(0);
            $table->unsignedInteger('no_discrepancies')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'account_id']);
            $table->index(['company_id', 'batch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
