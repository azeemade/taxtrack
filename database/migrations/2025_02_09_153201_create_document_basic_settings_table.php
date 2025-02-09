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
        Schema::create('document_basic_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('typography_setting_body_font_family')->nullable();
            $table->string('typography_setting_body_font_size')->nullable();
            $table->string('typography_setting_heading_font_family')->nullable();
            $table->string('typography_setting_heading_font_size')->nullable();
            $table->string('title_setting_draft_invoice')->nullable();
            $table->string('title_setting_approved_invoice')->nullable();
            $table->string('title_setting_overdue_invoice')->nullable();
            $table->string('title_setting_credit_note')->nullable();
            $table->string('title_setting_draft_purchase_order')->nullable();
            $table->string('title_setting_purchase_order')->nullable();
            $table->string('title_setting_draft_quote')->nullable();
            $table->string('title_setting_quote')->nullable();
            $table->string('title_setting_draft_bills')->nullable();
            $table->string('title_setting_bills')->nullable();
            $table->string('title_setting_receipt')->nullable();
            $table->json('content_display')->nullable();
            $table->text('document_contact_address')->nullable();
            $table->json('payment_term_for_invoice_and_bills')->nullable();
            $table->json('payment_term_for_quote')->nullable();
            $table->string('other_setting_primary_color')->nullable();
            $table->string('other_setting_secondary_color')->nullable();
            $table->string('other_setting_design_template')->nullable();
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
        Schema::dropIfExists('document_basic_settings');
    }
};
