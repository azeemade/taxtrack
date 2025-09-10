<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('finance_bank_statements', function (Blueprint $table) {
            $table->enum('reconciliation_status', ['unmatched','matched','mismatched'])->default('unmatched')->index();
            $table->timestamp('reconciled_at')->nullable()->index();
            // Keep reconciled_entry_id if you use it, but we'll rely on reconciliation_matches for multi-matches
            $table->unsignedBigInteger('reconciled_entry_id')->nullable()->index(); // optional/backcompat
        });
    }

    public function down()
    {
        Schema::table('finance_bank_statements', function (Blueprint $table) {
            $table->dropColumn(['reconciliation_status','reconciled_at','reconciled_entry_id']);
        });
    }
};