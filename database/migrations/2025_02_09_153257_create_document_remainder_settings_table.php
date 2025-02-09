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
        Schema::create('document_remainder_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('invoice_due_type', ['due-in', 'overdue-by'])->nullable();
            $table->unsignedInteger('invoice_due_days')->nullable();
            $table->string('email_remainder_title')->nullable();
            $table->mediumText('email_content')->nullable();
            $table->boolean('include_pdf_copy_of_invoice')->nullable();
            $table->text('email_cc')->nullable();
            $table->text('additional_attachment')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('company_id');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_remainder_settings');
    }
};
