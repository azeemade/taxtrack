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
        Schema::create('finance_account_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_type_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreign('account_type_id')->references('id')->on('finance_account_types')->onDelete('cascade');   
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_account_categories');
    }
};
