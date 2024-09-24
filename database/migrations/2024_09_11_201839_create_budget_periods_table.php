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
        Schema::create('budget_periods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('month');
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->unsignedBigInteger('budget_item_id');
            $table->unsignedBigInteger('budget_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('company_id');
            $table->foreign('budget_item_id')->references('id')->on('budget_items')->onDelete('cascade');
            $table->foreign('budget_id')->references('id')->on('budgets')->onDelete('cascade');
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
        Schema::dropIfExists('budget_periods');
    }
};
