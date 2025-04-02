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
        Schema::create('finance_account_transaction_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('edited_by');
            $table->string('name')->nullable();
            $table->string('payment_type')->nullable()->comment('payment', 'receipt');
            $table->enum('status', ['draft', 'pending', 'published', 'unpublished'])->default('draft'); 
            $table->foreign('edited_by')->references('id')->on('users')->onDelete('cascade');   
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_account_transaction_groups');
    }
};
