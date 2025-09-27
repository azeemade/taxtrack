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
        Schema::create('finance_chart_of_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_type_id');
            $table->unsignedBigInteger('account_category_id');
            $table->unsignedBigInteger('account_sub_category_id');
            $table->unsignedBigInteger('edited_by')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name');
            $table->string('account_number')->unique();
            $table->string('old_account_number')->nullable();
            $table->string('reference_code')->unique();
            $table->text('description')->nullable();
            $table->date('balance_date')->nullable();
            $table->string('slug')->unique();
            $table->string('currency')->nullable();
            $table->double('opening_balance', 15, 2)->default(0.00)->nullable();
            $table->double('balance', 15, 2)->default(0.00)->nullable();
            $table->enum('status', ['draft', 'pending', 'published', 'unpublished'])->default('unpublished');
            $table->enum('is_active', ['true', 'false'])->default('false');
            $table->enum('is_hidden', ['true', 'false'])->default('false');
            $table->enum('is_default', ['true', 'false'])->default('false');
            $table->foreign('account_type_id')->references('id')->on('finance_account_types')->onDelete('cascade');
            $table->foreign('account_category_id')->references('id')->on('finance_account_categories')->onDelete('cascade');
            $table->foreign('account_sub_category_id')->references('id')->on('finance_account_sub_categories')->onDelete('cascade');
            $table->foreign('edited_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_chart_of_accounts');
    }
};
