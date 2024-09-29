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
            $table->string('domain')->unique();
            $table->string('status')->default('approved')->comment('pending, approved, declined, suspended');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });


        Schema::table('users', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
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
