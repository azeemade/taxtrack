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
        Schema::create('payment_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('recordable_id');
            $table->string('recordable_type'); //purchase invoice, invoice, vendor bill
            $table->dateTime('paid_on');
            $table->decimal('amount_paid', 15, 2)->default(0.00);
            $table->decimal('amount_due', 15, 2)->default(0.00);
            $table->string('paymentID');
            $table->unsignedBigInteger('payment_method_id');
            $table->mediumText('payment_proof')->nullable();
            $table->mediumText('attachments')->nullable();
            $table->text('additional_notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('company_id');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods')->onDelete('cascade');
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
        Schema::dropIfExists('payment_records');
    }
};
