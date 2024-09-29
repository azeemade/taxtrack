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
        Schema::create('customers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('full_name');
            $table->string('display_name')->nullable();
            $table->string('salutation')->nullable();
            $table->string('customerID');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('type')->comment('business, individual');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('company_id');
            $table->string('primary_phone_ext');
            $table->string('primary_phone_number');
            $table->string('secondary_phone_ext')->nullable();
            $table->string('secondary_phone_number')->nullable();
            $table->string('primary_email')->unique();
            $table->string('secondary_email')->nullable();
            $table->unsignedBigInteger('currency_id');
            $table->unsignedBigInteger('country_id');
            $table->unsignedBigInteger('city_id');
            $table->string('primary_address');
            $table->string('secondary_address')->nullable();
            $table->string('zip_code')->nullable();
            $table->mediumText('statement_document_link')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
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
        Schema::dropIfExists('customers');
    }
};
