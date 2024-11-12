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
            $table->string('company_name')->nullable();
            // $table->string('salutation')->nullable();
            $table->string('customerID');
            $table->string('business_registration_number')->nullable();
            $table->string('vat_number')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('customer_type')->comment('business, individual, organization');
            $table->string('business_type')->nullable()->comment('proprietorship, partnership, corporation');
            $table->string('industry')->nullable();
            $table->unsignedInteger('employee_count')->default(1);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('company_id');
            $table->string('phone_ext');
            $table->string('phone_number');
            // $table->string('secondary_phone_ext')->nullable();
            // $table->string('secondary_phone_number')->nullable();
            $table->string('email')->unique();
            $table->decimal('current_balance', 20, 2);
            // $table->string('secondary_email')->nullable();
            $table->string('payment_term')->nullable();
            $table->unsignedBigInteger('currency_id');
            $table->unsignedBigInteger('country_id');
            $table->unsignedBigInteger('city_id');
            $table->string('address');
            // $table->string('secondary_address')->nullable();
            $table->string('zip_code')->nullable();
            $table->mediumText('special_instruction')->nullable();
            $table->mediumText('customer_logo')->nullable();
            $table->mediumText('statement_document_link')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
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
