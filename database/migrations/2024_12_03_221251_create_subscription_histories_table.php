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
        Schema::create('subscription_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('receipt_no')->nullable();
            $table->string('customer_refer_no')->nullable();
            $table->string('billed_per')->comment('month, year');
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->dateTime('subscribed_at');
            $table->dateTime('endDate');
            $table->string('status')->comment('pending, expired, active, cancelled');
            $table->string('payment_type');
            $table->string('paid_via')->nullable();
            $table->unsignedBigInteger('subscriber_id');
            $table->unsignedBigInteger('subscription_plan_id');
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
        Schema::dropIfExists('subscription_histories');
    }
};
