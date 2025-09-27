<?php

namespace App\Helpers;

use App\Models\FinanceAccountEntry;
use App\Models\FinanceChartOfAccount;
use Illuminate\Support\Facades\DB;

class AccountEntriesCalculationHelper
{
    public static function determineCreditOrDebitAccountBalanceSheet($account, $closingDebit, $closingCredit)
    {
        //check account type

        //              Entry	Increase	Decrease
        //Expenses	    Debit	Debit	Credit
        //Asset	        Debit	Debit	credit
        //Liabilities	Credit	Credit	debit
        //Equity	    Credit	Credit	debit
        //Revenue	    Credit	Credit	debit
        //Cost of sales	Debit	Debit	Debit	

        //Example
        /**
         * Access Bank Mpower is has an Asset Type which is always a Debit Account
         * 
         * Access bank Mpower	        3,000,000	Debit
         * Payment for shopping bags	500,000	Credit
         * Balance	2,500,000	Debit (It will fall under the debit side)
         * 
         * If Credit is more than the Debit
         * it will fall under credit side
         * 
         */

        $typeMapping = [
            "expense" => "debit",
            "asset" => "debit",
            "liability" => "credit",
            "equity" => "credit",
            "income" => "credit", //or revenue
        ];

        $type = $account->accountType->slug;
        if (isset($typeMapping[$type])) {
            $entryType = $typeMapping[$type];
            if ($entryType === "debit") {
                // If entry type is debit
                $closingDebit -= $closingCredit;
                $closingCredit = 0.00;
            } else {
                // If entry type is credit
                $closingCredit -= $closingDebit;
                $closingDebit = 0.00;
            }
        } else {
            $entryType = "unknown";
            $closingCredit = 0.00;
            $closingDebit = 0.00;
        }

        return [$entryType, $closingDebit, $closingCredit];
    }

    public static function calculateRetainedEarningsByDate($startDate, $endDate)
    {
        // $startDate = $request->start_date;
        // $endDate = $request->end_date;

        $retainedEarningsAccount = FinanceChartOfAccount::where('company_id',  auth()->user()->current_company_id)->where('slug', 'retained-earnings')->first();
        if (!$retainedEarningsAccount) {
            throw new \Exception("Retained earnings account not found.");
        }

        $openingRetainedEarnings = FinanceAccountEntry::where('account_id', $retainedEarningsAccount->id)
            ->whereHas('journalEntry', function ($query) {
                $query->where('status', "published");
            })
            ->where('transaction_date', '<', $startDate)
            ->sum(DB::raw('credit_amount - debit_amount'));

        $retainedEarningsEntries = FinanceAccountEntry::where('account_id', $retainedEarningsAccount->id)
            ->whereHas('journalEntry', function ($query) {
                $query->where('status', "published");
            })
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->sum(DB::raw('credit_amount - debit_amount'));
        $retainedEarnings = $openingRetainedEarnings + $retainedEarningsEntries;

        return $retainedEarnings;
    }
}
