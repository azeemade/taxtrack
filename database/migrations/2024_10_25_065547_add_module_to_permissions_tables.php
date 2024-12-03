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
        Schema::table('permissions_tables', function (Blueprint $table) {
            $table->string('app')->nullable();
            $table->string('module')->nullable();
            $table->string('submodule')->nullable();
            //
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions_tables', function (Blueprint $table) {
            //
        });
    }
};
