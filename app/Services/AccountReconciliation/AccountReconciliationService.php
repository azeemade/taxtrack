<?php

namespace App\Services\AccountReconciliation;

use App\Exceptions\BadRequestException;
use App\Exports\Banking\ReconciliationSummaryExport;
use App\Helpers\GeneralHelper;
use App\Models\FinanceAccountEntry;
use App\Models\FinanceBankStatement;
use App\Models\FinanceChartOfAccount;
use App\Models\FinanceJournalEntry;
use App\Models\ReconciliationMatch;
use App\Models\ReconciliationRecord;
use App\Models\ReconciliationRun;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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




    /**
     * Strict comparator:
     *  - Match ONLY when (bank.transaction_date == app.date) AND (bank.referenceID == app.reference)
     *  - Then compare amounts: withdrawals ↔ credit_amount, lodgments ↔ debit_amount
     *  - Returns raw lines for saving/preview
     */
    private function computeLinesForWindowStrict(int $companyId, int $accountId, string $startDate, string $endDate): array
    {
        $EPS = 0.01;

        $bankLines = \App\Models\FinanceBankStatement::query()
            ->where('company_id', $companyId)
            ->where('account_id', $accountId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')
            ->get();

        $appLines = \App\Models\FinanceAccountEntry::query()
            ->where('account_id', $accountId)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'published')->where('company_id', $companyId))
            ->orderBy('date')
            ->get();

        $makeKey = static fn(?string $date, ?string $ref) => ($date ?? '') . '|' . ($ref ?? '');
        $appsByKey = [];
        foreach ($appLines as $app) {
            $key = $makeKey(\Illuminate\Support\Carbon::parse($app->date)->toDateString(), (string) $app->reference);
            $appsByKey[$key] = $appsByKey[$key] ?? [];
            $appsByKey[$key][] = $app;
        }

        $results = [];

        foreach ($bankLines as $bank) {
            $date = \Illuminate\Support\Carbon::parse($bank->transaction_date)->toDateString();
            $ref  = (string) ($bank->referenceID ?? '');
            $key  = $makeKey($date, $ref);

            $bWithdrawals = (float) ($bank->withdrawals ?? 0.0);
            $bLodgments   = (float) ($bank->lodgments   ?? 0.0);

            if (!empty($ref) && !empty($appsByKey[$key])) {
                /** @var \App\Models\FinanceAccountEntry $app */
                $app = array_shift($appsByKey[$key]);

                $aCredit = (float) ($app->credit_amount ?? 0.0);
                $aDebit  = (float) ($app->debit_amount  ?? 0.0);

                $withdrawalsMatch = abs($bWithdrawals - $aCredit) <= $EPS;
                $lodgmentsMatch   = abs($bLodgments   - $aDebit)  <= $EPS;

                $isClean = $withdrawalsMatch && $lodgmentsMatch;

                $results[] = [
                    'bank_id'        => $bank->id,
                    'app_id'         => $app->id,
                    'date'           => $date,
                    'bank_reference' => $ref,
                    'app_reference'  => (string) $app->reference,
                    'bank_debit'     => $bWithdrawals, // bank debit = withdrawals
                    'bank_credit'    => $bLodgments,   // bank credit = lodgments
                    'app_debit'      => $aDebit,
                    'app_credit'     => $aCredit,
                    'status'         => $isClean ? 'matched' : 'mismatch',
                    'context'        => $isClean
                        ? 'fully matched by date+reference'
                        : implode(', ', array_filter([
                            $withdrawalsMatch ? null : 'withdrawals ≠ credit_amount',
                            $lodgmentsMatch   ? null : 'lodgments ≠ debit_amount',
                        ])),
                    'matched_by'     => 'date_and_reference',
                ];
            } else {
                $results[] = [
                    'bank_id'        => $bank->id,
                    'app_id'         => null,
                    'date'           => $date,
                    'bank_reference' => $ref ?: null,
                    'app_reference'  => null,
                    'bank_debit'     => $bWithdrawals,
                    'bank_credit'    => $bLodgments,
                    'app_debit'      => null,
                    'app_credit'     => null,
                    'status'         => 'unmatched',
                    'context'        => 'exists in bank only (no app line with same date+reference)',
                    'matched_by'     => null,
                ];
            }
        }

        // Remaining app lines are app-only discrepancies
        foreach ($appsByKey as $leftovers) {
            foreach ($leftovers as $app) {
                $results[] = [
                    'bank_id'        => null,
                    'app_id'         => $app->id,
                    'date'           => \Illuminate\Support\Carbon::parse($app->date)->toDateString(),
                    'bank_reference' => null,
                    'app_reference'  => (string) $app->reference,
                    'bank_debit'     => null,
                    'bank_credit'    => null,
                    'app_debit'      => (float) ($app->debit_amount  ?? 0.0),
                    'app_credit'     => (float) ($app->credit_amount ?? 0.0),
                    'status'         => 'unmatched',
                    'context'        => 'exists in app only (no bank line with same date+reference)',
                    'matched_by'     => null,
                ];
            }
        }

        return $results;
    }


    /**
     * Build the stat block from computed lines.
     * - counts of discrepancies / dual_reflections / no_discrepancies
     * - number of credit transactions on both sides
     * - number of debit transactions on both sides
     * - total credits on both sides
     * - total debits on both sides
     * - difference between total credits and total debits (per side + gap between sides)
     */
    private function summarizeLines(\Illuminate\Support\Collection $lines): array
    {
        $toF = static fn($v) => $v === null ? 0.0 : (float) $v;

        // Map statuses -> business labels
        $countNoDisc  = $lines->where('status', 'matched')->count();
        $countDual    = $lines->whereIn('status', ['mismatch', 'mismatched'])->count();
        $countDisc    = $lines->where('status', 'unmatched')->count();

        // Txn counts
        $bankCreditTxnCount = $lines->filter(fn($r) => $toF($r['bank_credit'] ?? null) > 0)->count();
        $bankDebitTxnCount  = $lines->filter(fn($r) => $toF($r['bank_debit'] ?? null)  > 0)->count();
        $appCreditTxnCount  = $lines->filter(fn($r) => $toF($r['app_credit']  ?? null) > 0)->count();
        $appDebitTxnCount   = $lines->filter(fn($r) => $toF($r['app_debit']   ?? null) > 0)->count();

        // Totals
        $bankTotalCredits = $lines->sum(fn($r) => $toF($r['bank_credit'] ?? null));
        $bankTotalDebits  = $lines->sum(fn($r) => $toF($r['bank_debit']  ?? null));
        $appTotalCredits  = $lines->sum(fn($r) => $toF($r['app_credit']  ?? null));
        $appTotalDebits   = $lines->sum(fn($r) => $toF($r['app_debit']   ?? null));

        // Differences (per side)
        $bankNet = $bankTotalCredits - $bankTotalDebits;
        $appNet  = $appTotalCredits  - $appTotalDebits;
        $netGap  = $bankNet - $appNet; // helpful reconciliation indicator

        return [
            'counts' => [
                'discrepancies'     => $countDisc,
                'dual_reflections'  => $countDual,
                'no_discrepancies'  => $countNoDisc,
            ],
            'transactions' => [
                'bank' => [
                    'credit_count' => $bankCreditTxnCount,
                    'debit_count'  => $bankDebitTxnCount,
                ],
                'app' => [
                    'credit_count' => $appCreditTxnCount,
                    'debit_count'  => $appDebitTxnCount,
                ],
            ],
            'totals' => [
                'bank' => [
                    'credits' => $bankTotalCredits,
                    'debits'  => $bankTotalDebits,
                    'net'     => $bankNet,     // credits - debits
                ],
                'app' => [
                    'credits' => $appTotalCredits,
                    'debits'  => $appTotalDebits,
                    'net'     => $appNet,      // credits - debits
                ],
                'net_gap_between_bank_and_app' => $netGap, // bank.net - app.net
            ],
        ];
    }










    public function buildReconciliationPreview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id'  => 'required|exists:finance_chart_of_accounts,id',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
            'batch_id'    => 'nullable|string',
            'save'        => 'nullable|boolean',   // default false
            'replace'     => 'nullable|boolean',   // when saving: wipe existing in window
        ]);
        if ($validator->fails()) {
            throw new BadRequestException($validator->errors()->first());
        }

        $companyId = Auth::user()->current_company_id;
        $accountId = (int) $request->account_id;
        $account = FinanceChartOfAccount::where('id', $accountId)
            ->where('company_id', $companyId)
            ->select('id', 'name')
            ->firstOrFail();
        $startDate = Carbon::parse($request->start_date)->toDateString();
        $endDate   = Carbon::parse($request->end_date)->toDateString();
        $batchId   = $account->name;
        $title   = $account->name;

        // 1) Build-only comparison — STRICT rule: same (date + ref) then amount mapping
        $lines = collect($this->computeLinesForWindowStrict($companyId, $accountId, $startDate, $endDate));

        // 2) Stats
        $stats = $this->summarizeLines($lines);

        // 3) Optional: save to DB
        $savedInfo = null;
        if ($request->boolean('save')) {

            $companyId = Auth::user()->current_company_id;
            $userId    = Auth::id();

            // create the run first (store counts for fast listing)
            $run = ReconciliationRun::create([
                'company_id'        => $companyId,
                'account_id'        => $accountId,
                'start_date'        => $startDate,
                'end_date'          => $endDate,
                'batch_id'          => $batchId,
                'title'             => $title,
                'discrepancies'     => $stats['counts']['discrepancies'],
                'dual_reflections'  => $stats['counts']['dual_reflections'],
                'no_discrepancies'  => $stats['counts']['no_discrepancies'],
                'created_by'        => $userId,
            ]);


            if ($request->boolean('replace')) {
                ReconciliationRecord::where('company_id', $companyId)
                    ->where('account_id', $accountId)
                    ->where('run_id', $run->id) // unlikely yet, but safe
                    ->delete();
            }

            $now = now();
            $payload = $lines->map(function ($row) use ($companyId, $accountId, $batchId, $now, $run) {
                $status = $row['status'] ?? 'unmatched';
                $classification = $status === 'matched'
                    ? 'no_discrepancy'
                    : (in_array($status, ['mismatch', 'mismatched'], true) ? 'dual_reflection' : 'discrepancy');

                return [
                    'company_id'      => $companyId,
                    'account_id'      => $accountId,
                    'run_id'          => $run->id,
                    'date'            => Carbon::parse($row['date'])->toDateString(),
                    'batch_id'        => $batchId ?? ($row['batch_id'] ?? null),

                    'bank_id'         => $row['bank_id'] ?? null,
                    'app_id'          => $row['app_id'] ?? null,
                    'bank_reference'  => $row['bank_reference'] ?? null,
                    'app_reference'   => $row['app_reference'] ?? null,

                    'bank_debit'      => $row['bank_debit'] ?? null,
                    'bank_credit'     => $row['bank_credit'] ?? null,
                    'app_debit'       => $row['app_debit'] ?? null,
                    'app_credit'      => $row['app_credit'] ?? null,

                    'classification'  => $classification,
                    'matched_by'      => $row['matched_by'] ?? null,
                    'context'         => $row['context'] ?? null,

                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            })->all();

            DB::transaction(function () use ($payload) {
                ReconciliationRecord::upsert(
                    $payload,
                    ['company_id', 'account_id', 'bank_id', 'app_id'],
                    ['date', 'batch_id', 'bank_reference', 'app_reference', 'bank_debit', 'bank_credit', 'app_debit', 'app_credit', 'classification', 'matched_by', 'context', 'updated_at']
                );
            });

            $savedInfo = ['enabled' => true, 'count' => count($payload)];
        }

        // 4) Response (build-only or build+save)
        return response()->json([
            'period'     => ['start_date' => $startDate, 'end_date' => $endDate],
            'account_id' => $accountId,
            'account_name' => $account->name,
            'batch_id'   => $batchId,
            'stats'      => $stats,     // ← stat block (see structure below)
            'lines'      => $lines->values(), // ← the exact line structure you requested
            'saved'      => $savedInfo ?: ['enabled' => false, 'count' => 0],
        ]);
    }


    public function getReconciliationSummaryFromRecords($request)
    {
        $validator = Validator::make($request->all(), [
            'account_id'  => 'required|exists:finance_chart_of_accounts,id',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'group_by'    => 'nullable|in:day,batch', // default: day
            'sort_by'     => 'nullable|in:date,discrepancies,dual_reflections,no_discrepancies',
            'sort_order'  => 'nullable|in:asc,desc',
            'search'      => 'nullable|string',
            'paginate'    => 'nullable|boolean',
            'limit'       => 'nullable|integer|min:1|max:200',
            'download'    => 'nullable|in:csv,xlsx',
            'batch_id'    => 'nullable|string',
        ]);
        if ($validator->fails()) {
            throw new BadRequestException($validator->errors()->first());
        }

        $companyId = Auth::user()->current_company_id;

        $query = ReconciliationRecord::query()
            ->where('company_id', $companyId)
            ->where('account_id', $request->account_id);

        // Date range (optional)
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        // Filter by batch (optional)
        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        // Grouping
        $groupBy = $request->get('group_by', 'day');
        if ($groupBy === 'batch') {
            $query->selectRaw("
            COALESCE(batch_id, '') as group_key,
            SUM(CASE WHEN classification = 'discrepancy' THEN 1 ELSE 0 END) as discrepancies,
            SUM(CASE WHEN classification = 'dual_reflection' THEN 1 ELSE 0 END) as dual_reflections,
            SUM(CASE WHEN classification = 'no_discrepancy' THEN 1 ELSE 0 END) as no_discrepancies,
            MAX(date) as date
        ")
                ->groupBy('group_key');
        } else {
            $query->selectRaw("
            date as group_key,
            SUM(CASE WHEN classification = 'discrepancy' THEN 1 ELSE 0 END) as discrepancies,
            SUM(CASE WHEN classification = 'dual_reflection' THEN 1 ELSE 0 END) as dual_reflections,
            SUM(CASE WHEN classification = 'no_discrepancy' THEN 1 ELSE 0 END) as no_discrepancies
        ")
                ->groupBy('date');
        }

        // Execute
        $rows = collect($query->get())->map(function ($r) use ($groupBy) {
            return [
                'date'             => $groupBy === 'batch' ? ($r->date ? Carbon::parse($r->date)->toDateString() : null) : $r->group_key,
                'batch_id'         => $groupBy === 'batch' ? ($r->group_key ?: null) : null,
                'discrepancies'    => (int) $r->discrepancies,
                'dual_reflections' => (int) $r->dual_reflections,
                'no_discrepancies' => (int) $r->no_discrepancies,
            ];
        });

        // Search (matches date or batch_id)
        if ($search = trim((string) $request->get('search', ''))) {
            $s = mb_strtolower($search);
            $rows = $rows->filter(function ($row) use ($s) {
                return str_contains(mb_strtolower((string)$row['date']), $s)
                    || ($row['batch_id'] && str_contains(mb_strtolower((string)$row['batch_id']), $s));
            })->values();
        }

        // Sort
        $sortBy    = $request->get('sort_by', 'date');
        $sortOrder = strtolower($request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $rows      = $rows->sortBy($sortBy, SORT_REGULAR, $sortOrder === 'desc')->values();

        // Export (optional)
        if ($download = $request->get('download')) {
            $filename = 'reconciliation-summary-' . now()->format('Ymd_His') . '.' . $download;
            return Excel::download(
                new ReconciliationSummaryExport($rows->toArray()),
                $filename
            );
        }

        // Paginate (default)
        if ($request->boolean('paginate', true)) {
            $page  = max(1, (int) $request->get('page', 1));
            $limit = max(1, (int) $request->get('limit', 20));
            $slice = $rows->forPage($page, $limit)->values();

            return new LengthAwarePaginator(
                $slice,
                $rows->count(),
                $limit,
                $page,
                ['path' => request()->url(), 'query' => request()->query()]
            );
        }

        return $rows;
    }


    public function listReconciliationRecords($request)
    {
        $validator = Validator::make($request->all(), [
            'account_id'  => 'required|exists:finance_chart_of_accounts,id',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'classification' => 'nullable|in:discrepancy,dual_reflection,no_discrepancy',
            'batch_id'    => 'nullable|string',
            'search'      => 'nullable|string',
            'sort_by'     => 'nullable|in:date,classification,bank_id,app_id',
            'sort_order'  => 'nullable|in:asc,desc',
            'paginate'    => 'nullable|boolean',
            'limit'       => 'nullable|integer|min:1|max:200',
        ]);
        if ($validator->fails()) {
            throw new BadRequestException($validator->errors()->first());
        }

        $companyId = Auth::user()->current_company_id;

        $q = ReconciliationRecord::where('company_id', $companyId)
            ->where('account_id', $request->account_id)
            ->when($request->filled('start_date') && $request->filled('end_date'), fn($qq) =>
            $qq->whereBetween('date', [$request->start_date, $request->end_date]))
            ->when($request->filled('classification'), fn($qq) =>
            $qq->where('classification', $request->classification))
            ->when($request->filled('batch_id'), fn($qq) =>
            $qq->where('batch_id', $request->batch_id));

        if ($s = trim((string) $request->get('search', ''))) {
            $q->where(function ($w) use ($s) {
                $w->where('bank_reference', 'like', "%$s%")
                    ->orWhere('app_reference', 'like', "%$s%")
                    ->orWhere('context', 'like', "%$s%");
            });
        }

        $sortBy = $request->get('sort_by', 'date');
        $sortOrder = $request->filled('sort_by')
            ? (strtolower($request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc')
            : 'desc';

        // $sortOrder = strtolower($request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $q->orderBy($sortBy, $sortOrder);

        if ($request->boolean('paginate', true)) {
            return $q->paginate($request->get('limit', 20));
        }

        return $q->get();
    }



    public function listReconciliationRuns(Request $request)
{
    $validator = \Validator::make($request->all(), [
        'account_id' => 'nullable|exists:finance_chart_of_accounts,id',
        'start_date' => 'nullable|date',
        'end_date'   => 'nullable|date|after_or_equal:start_date',
        'search'     => 'nullable|string', // matches title or batch_id
        'sort_by'    => 'nullable|in:created_at,start_date,end_date,discrepancies,dual_reflections,no_discrepancies',
        'sort_order' => 'nullable|in:asc,desc',
        'limit'      => 'nullable|integer|min:1|max:200',
        'page'       => 'nullable|integer|min:1',
    ]);
    if ($validator->fails()) {
        throw new BadRequestException($validator->errors()->first());
    }

    $companyId = \Auth::user()->current_company_id;

    $q = \App\Models\ReconciliationRun::query()
        ->where('company_id', $companyId)
        ->when($request->filled('account_id'), fn($qq) => $qq->where('account_id', $request->account_id))
        ->when($request->filled('start_date') && $request->filled('end_date'), fn($qq) =>
            $qq->where(function ($w) use ($request) {
                $w->whereBetween('start_date', [$request->start_date, $request->end_date])
                  ->orWhereBetween('end_date',   [$request->start_date, $request->end_date]);
            }))
        ->when($s = trim((string)$request->get('search', '')), fn($qq) =>
            $qq->where(function ($w) use ($s) {
                $w->where('title', 'like', "%{$s}%")
                  ->orWhere('batch_id', 'like', "%{$s}%");
            }));

    // sanitize sort inputs and defend against whitespace/casing issues
    $allowedSorts = ['created_at', 'start_date', 'end_date', 'discrepancies', 'dual_reflections', 'no_discrepancies'];
    $sortBy = trim((string)$request->get('sort_by', 'created_at'));
    if (!in_array($sortBy, $allowedSorts, true)) {
        $sortBy = 'created_at';
    }

    $sortOrder = strtolower(trim((string)$request->get('sort_order', 'desc'))) === 'asc' ? 'asc' : 'desc';

    $q->orderBy($sortBy, $sortOrder);

    $limit = (int)$request->get('limit', 20);
    $runs = $q->paginate($limit);

    // Shape each row for the list UI
    $runs->getCollection()->transform(function ($run) {
        return [
            'id'                => $run->id,
            'account_id'        => $run->account_id,
            'period'            => ['start_date' => $run->start_date, 'end_date' => $run->end_date],
            'batch_id'          => $run->batch_id,
            'title'             => $run->title,
            'counts'            => [
                'discrepancies'     => (int)$run->discrepancies,
                'dual_reflections'  => (int)$run->dual_reflections,
                'no_discrepancies'  => (int)$run->no_discrepancies,
            ],
            'created_at'        => $run->created_at,
        ];
    });

    return $runs;
}




    public function getReconciliationRun(Request $request, int $runId)
    {
        $companyId = \Auth::user()->current_company_id;

        /** @var \App\Models\ReconciliationRun $run */
        $run = ReconciliationRun::where('company_id', $companyId)->findOrFail($runId);

        // Lines can be large: paginate them optionally
        $validator = \Validator::make($request->all(), [
            'lines_limit' => 'nullable|integer|min:1|max:500',
            'lines_page'  => 'nullable|integer|min:1',
        ]);
        if ($validator->fails()) {
            throw new BadRequestException($validator->errors()->first());
        }

        $linesLimit = (int)($request->get('lines_limit', 100));
        $linesPage  = (int)($request->get('lines_page', 1));

        $linesQuery = ReconciliationRecord::where('company_id', $companyId)
            ->where('run_id', $run->id)
            ->orderBy('date')
            ->orderBy('id');

        $linesPaginator = $linesQuery->paginate($linesLimit, ['*'], 'page', $linesPage);

        // Build stats from records (or use run counts + recompute totals)
        $lines = collect($linesPaginator->items());
        $stats = $this->summarizeLines($linesQuery->get()->collect()); // full set for accurate totals

        return [
            'run' => [
                'id'          => $run->id,
                'account_id'  => $run->account_id,
                'period'      => ['start_date' => $run->start_date, 'end_date' => $run->end_date],
                'batch_id'    => $run->batch_id,
                'title'       => $run->title,
                'counts'      => [
                    'discrepancies'     => (int)$run->discrepancies,
                    'dual_reflections'  => (int)$run->dual_reflections,
                    'no_discrepancies'  => (int)$run->no_discrepancies,
                ],
                'created_at'  => $run->created_at,
            ],
            'stats' => $stats,                     // preview-style stats (counts + totals, etc.)
            'lines' => [                           // paginated lines, your requested shape
                'current_page' => $linesPaginator->currentPage(),
                'per_page'     => $linesPaginator->perPage(),
                'total'        => $linesPaginator->total(),
                'data'         => $lines->map(function ($r) {
                    return [
                        'bank_id'        => $r->bank_id,
                        'app_id'         => $r->app_id,
                        'date'           => $r->date,
                        'bank_reference' => $r->bank_reference,
                        'app_reference'  => $r->app_reference,
                        'bank_debit'     => $r->bank_debit,
                        'bank_credit'    => $r->bank_credit,
                        'app_debit'      => $r->app_debit,
                        'app_credit'     => $r->app_credit,
                        // derive status from classification, to match preview language
                        'status'         => $r->classification === 'no_discrepancy' ? 'matched' : ($r->classification === 'dual_reflection' ? 'mismatch' : 'unmatched'),
                        'context'        => $r->context,
                        'matched_by'     => $r->matched_by,
                    ];
                }),
            ],
        ];
    }
}
