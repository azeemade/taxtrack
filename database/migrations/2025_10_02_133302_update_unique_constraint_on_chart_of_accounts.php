<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_chart_of_accounts', function (Blueprint $table) {
            // Drop old unique index (check your actual name with SHOW INDEXES if different)
            $table->dropUnique('finance_chart_of_accounts_slug_unique');

            // Ensure company_id column type matches companies.id
            // Uncomment if needed:
            // $table->unsignedBigInteger('company_id')->change();

            // Add new composite unique
            $table->unique(['company_id', 'slug'], 'finance_company_slug_unique');


            $table->dropUnique('finance_chart_of_accounts_reference_code_unique');

            // Add company-scoped unique index
            $table->unique(['company_id', 'reference_code'], 'finance_company_reference_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('finance_chart_of_accounts', function (Blueprint $table) {
            $table->dropUnique('finance_company_slug_unique');
            $table->unique('slug', 'finance_chart_of_accounts_slug_unique');

            $table->dropUnique('finance_company_reference_code_unique');
            $table->unique('reference_code', 'finance_chart_of_accounts_reference_code_unique');
        });
    }
};
