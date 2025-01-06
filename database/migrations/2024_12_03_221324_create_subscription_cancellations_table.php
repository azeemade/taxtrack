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
        Schema::create('subscription_cancellations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('request_date');
            $table->date('effective_from');
            $table->string('status')->comment('pending, active');
            $table->string('reason');
            $table->string('additionalInformation')->nullable();
            $table->unsignedBigInteger('subscriber_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('subscription_plan_id');
            $table->unsignedBigInteger('subscription_history_id');
            $table->foreign('subscription_history_id')->references('id')->on('subscription_histories')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('subscriber_id')->references('id')->on('subscribers')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans')->onDelete('cascade')->onUpdate('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_cancellations');
    }
};
