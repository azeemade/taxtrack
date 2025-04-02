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
        Schema::create('finance_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('info')->default('JournalEntry');
            $table->foreignId('edited_by')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['draft', 'pending', 'published'])->default('draft'); 
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_journal_entries');
    }
};
