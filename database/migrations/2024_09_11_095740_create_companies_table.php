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
        Schema::create('companies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->unique();
            $table->text('address')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('companyUUID')->unique();
            $table->string('domain')->unique()->nullable();
            $table->string('industry')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('registration_id')->nullable();
            $table->string('fiscal_year_start')->nullable(); //month-day format
            $table->string('fiscal_year_end')->nullable(); //month-day format
            $table->string('status')->default('approved')->comment('pending, approved, declined, suspended');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onUpdate('cascade')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('current_company_id')->references('id')->on('companies')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
