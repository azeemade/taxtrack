<?php

namespace App\Services\AccountReconciliation;

use App\Exceptions\BadRequestException;
use App\Helpers\GeneralHelper;
use App\Models\FinanceAccountEntry;
use App\Models\FinanceBankStatement;
use App\Models\FinanceChartOfAccount;
use App\Models\FinanceJournalEntry;
use App\Models\ReconciliationMatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class AccountReconciliationService
{
    public function bankStatements($request)
    {
        $query = FinanceBankStatement::query()
            ->with(['account'])
            ->where('company_id', Auth::user()->current_company_id)
            ->orderBy('transaction_date', 'desc');

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('transaction_date', [$request->start_date, $request->end_date]);
        }

        return $query->paginate(20);
    }



    public function allAccountTransactions($request)
    {
        $query = FinanceAccountEntry::query()
            ->join('finance_journal_entries', 'finance_account_entries.journal_entry_id', '=', 'finance_journal_entries.id')
            ->whereHas('account.subCategory', function ($query) {
                $query->where('slug', 'cash-and-bank');
            })
            ->where('finance_journal_entries.company_id', Auth::user()->current_company_id)
            ->with(['account', 'journalEntry'])
            ->select('finance_account_entries.*')
            ->orderBy('finance_account_entries.transaction_date', 'desc');

        if ($request->filled('account_id')) {
            $query->where('finance_account_entries.account_id', $request->account_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('finance_account_entries.transaction_date', [
                $request->start_date,
                $request->end_date
            ]);
        }

        return $query->paginate(20);
    }


    public function getBankReconciliationSummary($request)
    {
        // Possible period_type values: specific_date, this_month, last_month, this_quarter, last_quarter, 
        //     this_year_to_current_date, this_quarter_to_current_date, this_month_to_current_date, 
        //     quarter_end, year_end, financial_year_end, default

        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:finance_chart_of_accounts,id',
            // 'date_input' => 'required|date',
            'period_type' => 'required|in:specific_date,this_month,quarter_end,year_end,financial_year_end,last_month,this_quarter,last_quarter,this_year_to_current_date,this_quarter_to_current_date,this_month_to_current_date,quarter_end,year_end,financial_year_end',
            'compare_with' => 'nullable|in:years_ago,quarters_ago,months_ago,days_ago,previous_period',
            'compare_value' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            throw new BadRequestException($validator->errors()->first());
        }

        $companyId = auth()->user()->current_company_id;
        $account = FinanceChartOfAccount::where('id', $request->account_id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        // Primary period
        $primaryPeriod = GeneralHelper::parseDateFilter($request->date_input, $request->period_type);
        $primaryData = $this->calculateReconciliationSummary($account, $primaryPeriod['start_date'], $primaryPeriod['end_date']);

        $data = [
            'primary' => array_merge(['period' => $primaryPeriod], $primaryData),
        ];

        // Comparison period if requested
        if ($request->compare_with) {
            $comparisonPeriod = $this->parseComparisonDate($request->date_input, $request->compare_with, $request->period_type, $request->compare_value ?? 1);
            $comparisonData = $this->calculateReconciliationSummary($account, $comparisonPeriod['start_date'], $comparisonPeriod['end_date']);
            $data['comparison'] = array_merge(['period' => $comparisonPeriod], $comparisonData);
        }

        $data['account'] = $account;
        $data['company'] = $account->company; // Assume relation

        return $data;
    }


    /**
     * Reconcile a single bank statement line to one or more account entries.
     *
     * $accountEntryMatches = [
     *   ['account_entry_id' => 12, 'matched_amount' => 10000],
     *   ['account_entry_id' => 13, 'matched_amount' => 40000],
     * ]
     */
    public function reconcileBankLine(int $bankStatementId, array $accountEntryMatches, $performedByUserId = null)
    {
        $performedByUserId = $performedByUserId ?? auth()->id();

        return DB::transaction(function () use ($bankStatementId, $accountEntryMatches, $performedByUserId) {
            $bankLine = FinanceBankStatement::findOrFail($bankStatementId);
            $companyId = auth()->user()->current_company_id;

            // fetch matched account entries
            $entries = FinanceAccountEntry::whereIn('id', array_column($accountEntryMatches, 'account_entry_id'))
                ->with('journalEntry')
                ->get()
                ->keyBy('id');

            // ensure all entries belong to same company and account (sanity)
            foreach ($entries as $e) {
                if ($e->journalEntry->company_id != $companyId) {
                    throw new BadRequestException('Entry does not belong to your company.');
                }
            }

            $totalMatched = 0;
            // create ReconciliationMatch rows
            foreach ($accountEntryMatches as $m) {
                $entryId = $m['account_entry_id'];
                $matchedAmount = (float) $m['matched_amount'];
                if (!isset($entries[$entryId])) {
                    throw new BadRequestException("Account entry {$entryId} not found.");
                }
                ReconciliationMatch::create([
                    'finance_bank_statement_id' => $bankLine->id,
                    'finance_account_entry_id' => $entryId,
                    'matched_amount' => $matchedAmount,
                    'created_by' => $performedByUserId,
                ]);
                $totalMatched += $matchedAmount;
            }

            // Compare totals
            $bankAmount = (float) ($bankLine->lodgments ?? 0) - (float) ($bankLine->withdrawals ?? 0);

            if (abs($totalMatched - $bankAmount) < 0.01) {
                // exact or within rounding -> mark matched
                $bankLine->reconciliation_status = 'matched';
                $bankLine->reconciled_at = now();
                $bankLine->save();
            } else {
                // difference exists -> create adjustment entry that links to the parent journal
                // pick a parent journal: prefer first matched entry's journal
                $firstMatched = $entries[$accountEntryMatches[0]['account_entry_id']];
                \Log::warning("firstMatched for company {$companyId} - {$firstMatched}");
                $parentJournal = $firstMatched->journalEntry;

                $difference = $bankAmount - $totalMatched; // positive means bank > app (need to increase app net)
                // Create adjustment journal entry and account entries
                $adjustment = $this->createAdjustmentForDifference($parentJournal, $bankLine->account_id, $difference, $performedByUserId);
                // Link the adjustment's account entry responsible for the bank account side to the bank line
                // We assume the adjustment returns the bank-side account entry id as 'bank_entry_id' in returned array
                ReconciliationMatch::create([
                    'finance_bank_statement_id' => $bankLine->id,
                    'finance_account_entry_id' => $adjustment['bank_entry_id'],
                    'matched_amount' => abs($difference),
                    'created_by' => $performedByUserId,
                ]);

                // Mark status mismatched -> but since adjustment now balances, we mark matched and record that it was mismatched then adjusted
                $bankLine->reconciliation_status = 'mismatched';
                $bankLine->reconciled_at = now();
                $bankLine->save();

                // set parent link for audit
                $adjustment['journal']->parent_journal_entry_id = $parentJournal->id;
                $adjustment['journal']->type = 'adjustment';
                $adjustment['journal']->status = 'published';
                $adjustment['journal']->save();
            }

            return [
                'bank_line' => $bankLine->fresh(),
                'total_matched' => $totalMatched,
            ];
        });
    }

    /**
     * Create adjustment JournalEntry and matching account entries that will adjust account balances to match the bank.
     *
     * @param FinanceJournalEntry $parentJournal
     * @param int $bankAccountId  // finance_chart_of_accounts id used for bank side
     * @param float $difference  // bank - matched_app_total. Could be positive or negative.
     * @return array  ['journal' => JournalEntry, 'bank_entry_id' => int, 'counter_entry_id' => int]
     */
    protected function createAdjustmentForDifference(FinanceJournalEntry $parentJournal, int $bankAccountId, float $difference, int $performedByUserId)
    {

        // difference > 0 means bank is higher than app -> need to increase Debit on bank or decrease credit depending on your config.
        // We'll create two account entries: one on bank account and one on counter account (use AR/GL from parent journal or a generic suspense account)
        // You should customize which counter account to use. Here we use the parent's opposite account (simplified).
        $companyId = auth()->user()->current_company_id;
        \Log::info("reconciliation got to createAdjustmentForDifference for company {$companyId}");

        $mainSuspenseAccount = FinanceChartOfAccount::where('company_id', $companyId)->orWhere('slug', 'suspense-account')->first();

        if (!$mainSuspenseAccount) {
            \Log::warning("Suspense account for company {$companyId} not found");
        }

        // pick counter account: try to reuse the first non-bank account entry from parentJournal
        $counterEntry = $parentJournal->accountEntries()->where('account_id', '!=', $bankAccountId)->first();
        $counterAccountId = $counterEntry ? $counterEntry->account_id : $mainSuspenseAccount->id ?? null;
        if (!$counterAccountId) {
            throw new BadRequestException('No counter account found for adjustment. Configure a suspense account.');
        }

        // Build adjustment journal
        $journal = FinanceJournalEntry::create([
            'description' => 'Adjustment for bank reconciliation - parent #' . $parentJournal->id,
            'date' => now()->toDateString(),
            'reference' => 'recon_adj_' . uniqid(),
            'company_id' => $companyId,
            'type' => 'adjustment',
            'status' => 'published',
            'parent_journal_entry_id' => $parentJournal->id,
            'total_amount' => abs($difference),
            'created_by' => $performedByUserId,
            'edited_by' => $performedByUserId,
        ]);

        // Decide debit/credit for bank vs counter. If difference > 0: bank needs DEBIT increase (receipt) or counter CREDIT.
        // This depends on your debit/credit convention. This example assumes bank receipts are debit_amount on bank account.
        if ($difference > 0) {
            // bank debit increases
            $bankEntry = FinanceAccountEntry::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $bankAccountId,
                'date' => now()->toDateString(),
                'transaction_date' => now()->toDateString(),
                'debit_amount' => abs($difference),
                'credit_amount' => 0,
                'amount' => abs($difference),
                'reference' => 'Reconciliation adjustment — bank side',
                'description' => 'Reconciliation adjustment — bank side',
                'edited_by' => auth()->user()->id
            ]);

            $counter = FinanceAccountEntry::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $counterAccountId,
                'date' => now()->toDateString(),
                'transaction_date' => now()->toDateString(),
                'debit_amount' => 0,
                'credit_amount' => abs($difference),
                'amount' => abs($difference),
                'reference' => 'Reconciliation adjustment — counter side',
                'description' => 'Reconciliation adjustment — counter side',
                'edited_by' => auth()->user()->id
            ]);
        } else {
            // difference < 0: bank is lower than app, so bank needs CREDIT increase (withdrawal) or counter DEBIT
            $bankEntry = FinanceAccountEntry::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $bankAccountId,
                'date' => now()->toDateString(),
                'transaction_date' => now()->toDateString(),
                'debit_amount' => 0,
                'credit_amount' => abs($difference),
                'amount' => abs($difference),
                'reference' => 'Reconciliation adjustment — bank side',
                'description' => 'Reconciliation adjustment — bank side',
                'edited_by' => auth()->user()->id
            ]);

            $counter = FinanceAccountEntry::create([
                'journal_entry_id' => $journal->id,
                'account_id' => $counterAccountId,
                'date' => now()->toDateString(),
                'transaction_date' => now()->toDateString(),
                'debit_amount' => abs($difference),
                'credit_amount' => 0,
                'amount' => abs($difference),
                'reference' => 'Reconciliation adjustment — counter side',
                'description' => 'Reconciliation adjustment — counter side',
                'edited_by' => auth()->user()->id
            ]);
        }

        return [
            'journal' => $journal,
            'bank_entry_id' => $bankEntry->id,
            'counter_entry_id' => $counter->id,
        ];
    }

    // From reconciliation perspective:

    // Bank Statement	        |       Your App (Bank Account)	    |       Meaning
    // Bank Debit (withdrawal)	        Credit in App	                    Bank balance goes down
    // Bank Credit (lodgement)	        Debit in App	                    Bank balance goes up


    public function getBankReconciliationLines($request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:finance_chart_of_accounts,id',
            'period_type' => 'required|in:specific_date,this_month,quarter_end,year_end,financial_year_end,last_month,this_quarter,last_quarter,this_year_to_current_date,this_quarter_to_current_date,this_month_to_current_date,quarter_end,year_end,financial_year_end',
            'compare_with' => 'nullable|in:years_ago,quarters_ago,months_ago,days_ago,previous_period',
            'compare_value' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            throw new BadRequestException($validator->errors()->first());
        }

        $companyId = auth()->user()->current_company_id;
        $account = FinanceChartOfAccount::where('id', $request->account_id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        $period = GeneralHelper::parseDateFilter($request->date_input, $request->period_type);
        $startDate = $period['start_date'];
        $endDate = $period['end_date'];

        // Fetch bank and app lines
        $bankLines = FinanceBankStatement::where('account_id', $account->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('company_id', $companyId)
            ->with('reconciliationMatches')
            ->get();

        $appLines = FinanceAccountEntry::where('account_id', $account->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'published')->where('company_id', $companyId))
            ->get();

        $results = [];

        // helper to compute debit/credit values
        $computeBankAmounts = function ($bank) {
            return [
                'bank_debit'  => (float) ($bank->withdrawals ?? 0),
                'bank_credit' => (float) ($bank->lodgments ?? 0),
            ];
        };

        $computeAppAmounts = function ($app) {
            return [
                'app_debit'  => (float) ($app->debit_amount ?? 0),
                'app_credit' => (float) ($app->credit_amount ?? 0),
            ];
        };

        // convert to mutable collection
        $appLines = $appLines->values();

        foreach ($bankLines as $bank) {
            $bankCalc = $computeBankAmounts($bank);
            $match = null;
            $matchedApp = null;
            $matchMethod = null;
            $lineContext = [];
            $status = 'unmatched';

            // Check if already reconciled
            if ($bank->reconciliation_status === 'matched' || $bank->reconciliation_status === 'mismatched') {
                $matches = $bank->reconciliationMatches;
                $matchedAppIds = $matches->pluck('finance_account_entry_id')->toArray();
                $matchedApp = $appLines->firstWhere('id', $matchedAppIds[0] ?? null);
                $status = $bank->reconciliation_status;
                $context = $bank->reconciliation_status === 'matched' ? 'fully matched' : 'mismatched but reconciled with adjustment';
                $matchMethod = 'manual_reconciliation';
            } else {
                // Try reference match
                $match = $appLines->first(function ($app) use ($bank) {
                    return (!empty($bank->referenceID) && !empty($app->reference) && $app->reference === $bank->referenceID);
                });
                if ($match) {
                    $matchedApp = $match;
                    $matchMethod = 'reference';
                }
            }

            if ($matchedApp) {
                $appCalc = $computeAppAmounts($matchedApp);

                // Only check amounts if not already reconciled
                if ($status !== 'matched' && $status !== 'mismatched') {
                    if (abs($bankCalc['bank_debit'] - $appCalc['app_credit']) > 0.01) {
                        $lineContext[] = 'withdrawal mismatch';
                    }
                    if (abs($bankCalc['bank_credit'] - $appCalc['app_debit']) > 0.01) {
                        $lineContext[] = 'lodgement mismatch';
                    }
                    if (($bank->referenceID ?? '') !== ($matchedApp->reference ?? '')) {
                        $lineContext[] = 'reference mismatch';
                    }
                    $status = empty($lineContext) ? 'matched' : 'mismatch';
                    $context = empty($lineContext) ? 'fully matched' : implode(', ', $lineContext);
                }

                $results[] = [
                    'bank_id'       => $bank->id,
                    'app_id'        => $matchedApp->id,
                    'date'          => $bank->transaction_date,
                    'bank_reference' => $bank->referenceID,
                    'app_reference' => $matchedApp->reference,
                    'bank_debit'    => $bankCalc['bank_debit'],
                    'bank_credit'   => $bankCalc['bank_credit'],
                    'app_debit'     => $appCalc['app_debit'],
                    'app_credit'    => $appCalc['app_credit'],
                    'status'        => $status,
                    'context'       => $context,
                    'matched_by'    => $matchMethod,
                ];

                $appLines = $appLines->reject(fn($a) => $a->id === $matchedApp->id)->values();
            } else {
                $results[] = [
                    'bank_id'       => $bank->id,
                    'app_id'        => null,
                    'date'          => $bank->transaction_date,
                    'bank_reference' => $bank->referenceID,
                    'app_reference' => null,
                    'bank_debit'    => $bankCalc['bank_debit'],
                    'bank_credit'   => $bankCalc['bank_credit'],
                    'app_debit'     => null,
                    'app_credit'    => null,
                    'status'        => 'unmatched',
                    'context'       => 'exists in bank only',
                    'matched_by'    => null,
                ];
            }
        }

        // foreach ($bankLines as $bank) {
        //     $bankCalc = $computeBankAmounts($bank);

        //     // 1) Try reference match
        //     $match = $appLines->first(function ($app) use ($bank) {
        //         return (!empty($bank->referenceID) && !empty($app->reference) && $app->reference === $bank->referenceID);
        //     });

        //     $matchedApp = null;
        //     $matchMethod = null;
        //     $lineContext = [];
        //     $status = 'unmatched';

        //     if ($match) {
        //         $matchedApp = $match;
        //         $matchMethod = 'reference';
        //     }

        //     if ($matchedApp) {
        //         $appCalc = $computeAppAmounts($matchedApp);

        //         // Compare debit
        //         if (abs($bankCalc['bank_debit'] - $appCalc['app_credit']) > 0.01) {
        //             $lineContext[] = 'withdrawal mismatch';
        //         }

        //         // Compare credit
        //         if (abs($bankCalc['bank_credit'] - $appCalc['app_debit']) > 0.01) {
        //             $lineContext[] = 'lodgement mismatch';
        //         }

        //         // Compare reference (should always match here, but keep check)
        //         if (($bank->referenceID ?? '') !== ($matchedApp->reference ?? '')) {
        //             $lineContext[] = 'reference mismatch';
        //         }

        //         $status  = empty($lineContext) ? 'matched' : 'mismatch';
        //         $context = empty($lineContext) ? 'fully matched' : implode(', ', $lineContext);

        //         $results[] = [
        //             'bank_id'       => $bank->id,
        //             'app_id'        => $matchedApp->id,
        //             'date'          => $bank->transaction_date,
        //             'bank_reference' => $bank->referenceID,
        //             'app_reference' => $matchedApp->reference,
        //             'bank_debit'    => $bankCalc['bank_debit'],
        //             'bank_credit'   => $bankCalc['bank_credit'],
        //             'app_debit'     => $appCalc['app_debit'],
        //             'app_credit'    => $appCalc['app_credit'],
        //             'status'        => $status,
        //             'context'       => $context,
        //             'matched_by'    => $matchMethod,
        //         ];

        //         // remove matched app entry
        //         $appLines = $appLines->reject(fn($a) => $a->id === $matchedApp->id)->values();
        //     } else {
        //         // unmatched bank line
        //         $results[] = [
        //             'bank_id'       => $bank->id,
        //             'app_id'        => null,
        //             'date'          => $bank->transaction_date,
        //             'bank_reference' => $bank->referenceID,
        //             'app_reference' => null,
        //             'bank_debit'    => $bankCalc['bank_debit'],
        //             'bank_credit'   => $bankCalc['bank_credit'],
        //             'app_debit'     => null,
        //             'app_credit'    => null,
        //             'status'        => 'unmatched',
        //             'context'       => 'exists in bank only',
        //             'matched_by'    => null,
        //         ];
        //     }
        // }

        // handle app entries that were not matched
        // foreach ($appLines as $app) {
        //     $appCalc = $computeAppAmounts($app);
        //     $results[] = [
        //         'bank_id'       => null,
        //         'app_id'        => $app->id,
        //         'date'          => $app->date,
        //         'bank_reference' => null,
        //         'app_reference' => $app->reference,
        //         'bank_debit'    => null,
        //         'bank_credit'   => null,
        //         'app_debit'     => $appCalc['app_debit'],
        //         'app_credit'    => $appCalc['app_credit'],
        //         'status'        => 'unmatched',
        //         'context'       => 'exists in app only',
        //         'matched_by'    => null,
        //     ];
        // }

        foreach ($appLines as $app) {
            $appCalc = $computeAppAmounts($app);
            $results[] = [
                'bank_id'       => null,
                'app_id'        => $app->id,
                'date'          => $app->date,
                'bank_reference' => null,
                'app_reference' => $app->reference,
                'bank_debit'    => null,
                'bank_credit'   => null,
                'app_debit'     => $appCalc['app_debit'],
                'app_credit'    => $appCalc['app_credit'],
                'status'        => 'unmatched',
                'context'       => 'exists in app only',
                'matched_by'    => null,
            ];
        }

        return [
            'period'  => $period,
            'account' => $account,
            'lines'   => $results,
        ];
    }

    private function calculateReconciliationSummary($account, $startDate, $endDate)
    {
        $accountId = $account->id;

        // Balance in app (ledger balance as at end_date)
        $entries = FinanceAccountEntry::where('account_id', $accountId)
            ->where('date', '<=', $endDate)
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'published')->where('company_id', auth()->user()->current_company_id))
            ->get();

        $debitSum = $entries->sum('debit_amount');
        $creditSum = $entries->sum('credit_amount');
        $balanceInApp = ($account->opening_balance ?? 0) + $debitSum - $creditSum;

        // Reconciled entry IDs (from bank statements)
        $reconciledEntryIds = FinanceBankStatement::where('account_id', $accountId)
            ->whereNotNull('reconciled_entry_id')
            ->where('transaction_date', '<=', $endDate)
            ->pluck('reconciled_entry_id');

        // Outstanding app entries (unreconciled)
        $outstandingEntries = $entries->whereNotIn('id', $reconciledEntryIds);

        $outstandingPayments = $outstandingEntries->where('credit_amount', '>', 0)->sum('credit_amount'); // Payments out (credits)
        $outstandingReceipts = $outstandingEntries->where('debit_amount', '>', 0)->sum('debit_amount'); // Receipts in (debits)

        // Unreconciled bank statement lines
        $unreconciledBankLines = FinanceBankStatement::where('account_id', $accountId)
            ->whereNull('reconciled_entry_id')
            ->where('transaction_date', '<=', $endDate)
            ->get();

        $unreconciledNet = $unreconciledBankLines->sum('lodgments') - $unreconciledBankLines->sum('withdrawals');

        // Calculated statement balance
        $calculatedStatementBalance = $balanceInApp + $outstandingPayments - $outstandingReceipts + $unreconciledNet;

        // Imported statement balance (last running balance <= end_date)
        $importedBalance = null;
        $lastBankStatement = FinanceBankStatement::where('account_id', $accountId)
            ->where('transaction_date', '<=', $endDate)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastBankStatement) {
            $importedBalance = $lastBankStatement->balance;
        }

        return [
            'balance_in_app' => $balanceInApp,
            'outstanding_payments' => $outstandingPayments,
            'outstanding_receipts' => $outstandingReceipts,
            'unreconciled_statement_lines_net' => $unreconciledNet,
            'calculated_statement_balance' => $calculatedStatementBalance,
            'imported_statement_balance' => $importedBalance,
        ];
    }
}
