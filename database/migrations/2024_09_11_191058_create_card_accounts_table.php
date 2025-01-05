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
        Schema::create('card_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('issuer_number');
            $table->string('holder_name');
            $table->string('cvv');
            $table->string('expiration_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('billing_address')->nullable();
            $table->string('billing_postal_code')->nullable();
            $table->unsignedBigInteger('billing_country_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('card_brand_id');
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->unsignedBigInteger('issuing_bank_id')->nullable();
            $table->foreign('issuing_bank_id')->references('id')->on('banks')->onDelete('set null');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');
            $table->foreign('billing_country_id')->references('id')->on('countries')->onDelete('cascade');
            $table->foreign('card_brand_id')->references('id')->on('card_brands')->onDelete('cascade');
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
        Schema::dropIfExists('card_accounts');
    }
};
