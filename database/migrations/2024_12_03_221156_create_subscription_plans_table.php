<?php

use App\Constants\SubscriptionConstant;
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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->decimal('monthly_fee', 15, 2)->default(0.00);
            $table->decimal('yearly_fee', 15, 2)->default(0.00);
            $table->text('short_description')->nullable();
            $table->string('primary_cta_text')->nullable();
            $table->string('primary_link')->nullable();
            $table->string('secondary_cta')->nullable();
            $table->string('secondary_link')->nullable();
            $table->string('provider_product_id')->nullable(); // stripe product id
            $table->json('provider_price_ids')->nullable(); // stripe price id object
            $table->boolean('is_active')->default(false);
            $table->boolean('is_free')->default(false);
            $table->unsignedInteger('duration')->default(30);
            $table->unsignedInteger('default_seat')->default(SubscriptionConstant::DEFAULT_SEAT_COUNT);
            $table->json('seat_amount')->nullable(); //{"monthly": 20, yearly: 40}
            $table->json('provider_seat_amount_ids')->nullable();
            $table->string('status')->default('active')->comment('active, inactive');
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
