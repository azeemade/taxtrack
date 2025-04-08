<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class FinanceTotalsHelper
{
    public static function getAccountTypeTotal($typeSlug, $startDate, $endDate)
    {
        return DB::table('finance_account_entries')
            ->join('finance_journal_entries', 'finance_account_entries.journal_entry_id', '=', 'finance_journal_entries.id')
            ->join('finance_chart_of_accounts', 'finance_account_entries.account_id', '=', 'finance_chart_of_accounts.id')
            ->join('finance_account_categories', 'finance_chart_of_accounts.account_category_id', '=', 'finance_account_categories.id')
            ->join('finance_account_types', 'finance_account_categories.account_type_id', '=', 'finance_account_types.id')
            ->where('finance_journal_entries.status', "published")
            ->where('finance_account_types.slug', $typeSlug)
            ->whereBetween('finance_account_entries.date', [$startDate, $endDate])
            ->select(DB::raw('COALESCE(SUM(finance_account_entries.debit_amount - finance_account_entries.credit_amount), 0) as total'))
            ->first()
            ->total;
    }

    public static function getAccountCategoryTotal($categorySlug, $startDate, $endDate)
    {
        return DB::table('finance_account_entries')
            ->join('finance_journal_entries', 'finance_account_entries.journal_entry_id', '=', 'finance_journal_entries.id')
            ->join('finance_chart_of_accounts', 'finance_account_entries.account_id', '=', 'finance_chart_of_accounts.id')
            ->join('finance_account_categories', 'finance_chart_of_accounts.account_category_id', '=', 'finance_account_categories.id')
            ->where('finance_journal_entries.status', "published")
            ->where('finance_account_categories.slug', $categorySlug)
            ->whereBetween('finance_account_entries.date', [$startDate, $endDate])
            ->select(DB::raw('COALESCE(SUM(finance_account_entries.debit_amount - finance_account_entries.credit_amount), 0) as total'))
            ->first()
            ->total;
    }
}
