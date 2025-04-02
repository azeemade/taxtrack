<?php

namespace App\Helpers;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class FinanceAccountBalanceHelper
{
    public static function calculateCurrentBalance($account, $startDate = null, $endDate = null, $useDateFilter = false)
    {
        // Get entries with optional date filtering
        $entries = $account->accountEntries;

        if ($useDateFilter && $startDate && $endDate) {
            $entries = $entries->filter(function ($entry) use ($startDate, $endDate) {
                return $entry->date >= $startDate && $entry->date <= $endDate;
            });
        }

        $totalDebit = $entries->sum('debit_amount');
        $totalCredit = $entries->sum('credit_amount');

        // Determine balance based on account type
        list($entryType, $balance) = self::determineAccountBalance(
            $account->accountType->slug,
            $account->opening_balance,
            $totalDebit,
            $totalCredit
        );

        return [
            'current_balance' => $balance,
            'balance_type' => $entryType,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'date_filter_applied' => $useDateFilter,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
    }

    public static function determineAccountBalance($accountTypeSlug, $openingBalance, $totalDebit, $totalCredit)
    {
        $typeMapping = [
            "expense" => "debit",
            "asset" => "debit",
            "liability" => "credit",
            "equity" => "credit",
            "income" => "credit",
            "revenue" => "credit", //income is the same as revenue
        ];

        if (!isset($typeMapping[$accountTypeSlug])) {
            return ['unknown', 0.00];
        }

        $entryType = $typeMapping[$accountTypeSlug];

        if ($entryType === "debit") {
            // For debit accounts (assets, expenses):
            // Opening + Debits - Credits
            $balance = $openingBalance + $totalDebit - $totalCredit;
            $balanceType = $balance >= 0 ? 'debit' : 'credit';
        } else {
            // For credit accounts (liabilities, equity, income):
            // Opening + Credits - Debits
            $balance = $openingBalance + $totalCredit - $totalDebit;
            $balanceType = $balance >= 0 ? 'credit' : 'debit';
        }

        return [$balanceType, abs($balance)];
    }
}
