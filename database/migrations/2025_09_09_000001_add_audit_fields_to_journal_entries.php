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
        Schema::table('finance_journal_entries', function (Blueprint $table) {
            $table->enum('status', ['draft', 'pending', 'published', 'unpublished', 'voided'])->default('unpublished')->change();
            
            // Add the new fields
            $table->unsignedBigInteger('parent_journal_entry_id')->nullable()->index();
            $table->enum('type', ['original', 'adjustment', 'reversal'])->default('original')->index();
            
            // Add foreign key constraint
            $table->foreign('parent_journal_entry_id')
                  ->references('id')
                  ->on('finance_journal_entries')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_journal_entries', function (Blueprint $table) {
            $table->dropForeign(['parent_journal_entry_id']);
            $table->dropColumn(['parent_journal_entry_id', 'type']);
            
            // Revert status to original values
            $table->enum('status', ['draft', 'pending', 'published', 'unpublished'])->default('unpublished')->change();
        });
    }
};