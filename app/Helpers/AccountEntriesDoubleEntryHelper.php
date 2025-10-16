<?php

namespace App\Helpers;

use App\Models\FinanceAccountEntry as FinanceAccountEntry;
use App\Models\FinanceChartOfAccount;
use Illuminate\Support\Facades\DB;

class AccountEntriesDoubleEntryHelper
{
    public static function doubleEntry($journalEntryID, $date, $debitAccountID, $creditAccountID, $amount, $description, $reference, $bankFee = 0, $exchangeRate = 1)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();

            $exchangeRate = $exchangeRate == 0.00 ? 1 : $exchangeRate;
            // Apply exchange rate
            $companyDefaultCurrencyAmount = $amount * $exchangeRate;

            // create account debit entry for account
            $debitEntry = FinanceAccountEntry::create([
                'journal_entry_id' => $journalEntryID,
                'date' => $date,
                'transaction_date' => $date,
                'account_id' => $debitAccountID,
                'amount' => $companyDefaultCurrencyAmount,
                'reference' => $reference,
                'description' => $description,
                'debit_amount' => $companyDefaultCurrencyAmount,
                'credit_amount' => 0.00,
                'edited_by' => $user->id,
            ]);

            if (!$debitEntry) {
                throw new \Exception("Debit entry creation failed");
            }

            // create account credit entry for account
            $creditEntry = FinanceAccountEntry::create([
                'journal_entry_id' => $journalEntryID,
                'date' => $date,
                'transaction_date' => $date,
                'account_id' => $creditAccountID,
                'amount' => $companyDefaultCurrencyAmount,
                'reference' => $reference,
                'description' => $description,
                'debit_amount' => 0.00,
                'credit_amount' => $companyDefaultCurrencyAmount,
                'edited_by' => $user->id,
            ]);

            if (!$creditEntry) {
                throw new \Exception("Credit entry creation failed");
            }

            // REMOVED: Bank fee handling - should be separate transaction

            DB::commit();
            return null;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    // public static function doubleEntry($journalEntryID, $date, $debitAccountID, $creditAccountID, $amount, $description, $reference, $bankFee = 0, $exchangeRate = 1)
    // {
    //     $companyID = auth()->user()->current_company_id;
    //     $bankChargeAccount = FinanceChartOfAccount::where('slug', 'bank-charges')->where('company_id', $companyID)->first();
    //     if (!$bankChargeAccount) {
    //         throw new \Exception("Unable to fetch bank fee account");
    //     }

    //     DB::beginTransaction();
    //     try {
    //         $user = auth()->user();

    //         $exchangeRate = $exchangeRate == 0.00 ? 1 : $exchangeRate;
    //         // Apply exchange rate
    //         $companyDefaultCurrencyAmount = $amount * $exchangeRate;
    //         $companyDefaultCurrencyFee = $bankFee * $exchangeRate;

    //         // create account debit entry for account
    //         $debitEntry = FinanceAccountEntry::create([
    //             'journal_entry_id' => $journalEntryID,
    //             'date' => $date,
    //             'transaction_date' => $date,
    //             'account_id' => $debitAccountID,
    //             'amount' => $companyDefaultCurrencyAmount,
    //             'reference' => $reference,
    //             'description' => $description,
    //             'debit_amount' => $companyDefaultCurrencyAmount,
    //             'credit_amount' => 0.00,
    //             'edited_by' => $user->id,
    //         ]);

    //         if (!$debitEntry) {
    //             throw new \Exception("Debit entry creation failed");
    //         }

    //         // create account credit entry for account
    //         $creditEntry = FinanceAccountEntry::create([
    //             'journal_entry_id' => $journalEntryID,
    //             'date' => $date,
    //             'transaction_date' => $date,
    //             'account_id' => $creditAccountID,
    //             'amount' => $companyDefaultCurrencyAmount,
    //             'reference' => $reference,
    //             'description' => $description,
    //             'debit_amount' => 0.00,
    //             'credit_amount' => $companyDefaultCurrencyAmount,
    //             'edited_by' => $user->id,
    //         ]);

    //         if (!$creditEntry) {
    //             throw new \Exception("Credit entry creation failed");
    //         }

    //         // Debit the bank fee (if any)
    //         if ($bankFee > 0) {
    //             FinanceAccountEntry::create([
    //                 'journal_entry_id' => $journalEntryID,
    //                 'date' => $date,
    //                 'transaction_date' => $date,
    //                 'account_id' => $bankChargeAccount->id,
    //                 'amount' => $companyDefaultCurrencyFee,
    //                 'reference' => $reference . "-FEE",
    //                 'description' => "Bank Fee - " . $description,
    //                 'debit_amount' => $companyDefaultCurrencyFee,
    //                 'credit_amount' => 0.00,
    //                 'edited_by' => $user->id,
    //             ]);
    //         }


    //         // $dataToLog = [
    //         //     'causer_id' => auth()->user()->id,
    //         //     'action_id' => $journalEntryID,
    //         //     'action_type' => "Models\FinanceJournalEntry",
    //         //     'log_name' => "Finance Journal Entry created successfully",
    //         //     'description' => "Finance Journal Entry transaction created successfully by {$user->lastname} {$user->firstname}",
    //         // ];

    //         // ProcessAuditLog::storeAuditLog($dataToLog);
    //         DB::commit();

    //         return null;
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         throw $th;
    //     }
    // }
}
