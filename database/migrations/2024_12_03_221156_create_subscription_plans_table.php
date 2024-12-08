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
            $table->boolean('is_active')->default(false);
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
