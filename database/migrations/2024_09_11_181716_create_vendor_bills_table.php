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
        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('purchase_invoice_id')->nullable();
            $table->date('purchase_invoice_due_date')->nullable();
            $table->string('share_status')->comment('shared, not-shared');
            $table->string('vendor_billID');
            $table->date('vendor_bill_due_date')->nullable();
            $table->decimal('sub_total', 15, 2)->default(0.00);
            $table->decimal('shipping_charge', 15, 2)->default(0.00);
            $table->decimal('additional_charge', 15, 2)->default(0.00);
            $table->decimal('discount', 15, 2)->default(0.00);
            $table->decimal('vat', 15, 2)->default(0.00);
            $table->decimal('tax', 15, 2)->default(0.00);
            $table->decimal('vendor_bill_total', 15, 2)->default(0.00);
            $table->string('status')->default('draft')->comment('draft, issued');
            $table->mediumText('attachments')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('additional_comment')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->date('recurring_next_due_date')->nullable();
            $table->date('recurring_start_date')->nullable();
            $table->date('recurring_end_date')->nullable();
            $table->unsignedInteger('repeat')->default(1);
            $table->string('repeat_period')->comment('month, day, week, year');
            $table->mediumText('preview_link')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->foreign('parent_id')->references('id')->on('vendor_bills')->onDelete('set null');
            $table->foreign('purchase_invoice_id')->references('id')->on('purchase_invoices')->onDelete('set null');
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
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
        Schema::dropIfExists('vendor_bills');
    }
};
