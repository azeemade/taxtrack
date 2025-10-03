<?php

namespace App\Services\Transaction;

use App\Exceptions\BadRequestException;
use App\Exports\Banking\FinanceTransactionGroupExport;
use App\Exports\Banking\TransactionExport;
use App\Helpers\AccountEntriesDoubleEntryHelper;
use App\Helpers\GeneralHelper;
use App\Models\FinanceAccountTransaction;
use App\Models\FinanceAccountTransactionGroup;
use App\Models\FinanceJournalEntry;
use App\Models\PaymentRecord;
use App\Responser\JsonResponser;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel;

class TransactionService
{
    public function updatePaymentMethodTransaction($request)
    {
        $record = PaymentRecord::find($request['id']);
        if (!$record) {
            throw new BadRequestException("Record not found", Response::HTTP_NOT_FOUND);
        }

        $record->update($request);

        return $record;
    }


    // public function getTransactionOverview($request)
    // {
    //     $query = FinanceAccountTransaction::query();

    //     if ($request->filled('start_date')) {
    //         $query->whereDate('transaction_date', '>=', $request->start_date);
    //     }
    //     if ($request->filled('end_date')) {
    //         $query->whereDate('transaction_date', '<=', $request->end_date);
    //     }

    //     $inflowAmount = (clone $query)->where('type', 'Income')->sum('amount');
    //     $inflowCount = (clone $query)->where('type', 'Income')->count();

    //     $outflowAmount = (clone $query)->where('type', 'Expense')->sum('amount');
    //     $outflowCount = (clone $query)->where('type', 'Expense')->count();

    //     return [
    //         'total_inflow_amount' => $inflowAmount,
    //         'inflow_count' => $inflowCount,
    //         'total_outflow_amount' => $outflowAmount,
    //         'outflow_count' => $outflowCount,
    //         'balance' => $inflowAmount - $outflowAmount,
    //     ];
    // }

    //For overview totals you should only aggregate the main bank side
    public function getTransactionOverview($request)
    {
        $user = auth()->user();

        $base = FinanceAccountTransaction::query()
            // only count the main bank side to avoid double-counting
            ->where(function ($q) {
                // handle string or tinyint storage
                $q->where('mainBank', 'true')->orWhere('mainBank', 1);
            })
            // scope to the current company through the group relation
            ->whereHas('financeTransactionGroup', function ($q) use ($user, $request) {
                $q->where('company_id', $user->current_company_id)
                    ->when($request->filled('status'), fn($qq) => $qq->where('status', $request->status));
            })
            // date filters (use transaction_date)
            ->when($request->filled('start_date'), fn($q) => $q->whereDate('transaction_date', '>=', $request->start_date))
            ->when($request->filled('end_date'),   fn($q) => $q->whereDate('transaction_date', '<=', $request->end_date))
            // optional extra filters
            ->when($request->filled('payment_type'), fn($q) => $q->where('payment_type', $request->payment_type))
            ->when($request->filled('account_id'),   fn($q) => $q->where('account_id', $request->account_id));

        // single aggregate query
        $row = $base->selectRaw("
        COALESCE(SUM(CASE WHEN type = 'Income'  THEN amount ELSE 0 END), 0) AS inflow_amount,
        COALESCE(SUM(CASE WHEN type = 'Expense' THEN amount ELSE 0 END), 0) AS outflow_amount,
        COALESCE(SUM(CASE WHEN type = 'Income'  THEN 1      ELSE 0 END), 0) AS inflow_count,
        COALESCE(SUM(CASE WHEN type = 'Expense' THEN 1      ELSE 0 END), 0) AS outflow_count
    ")->first();

        $in  = (float) ($row->inflow_amount ?? 0);
        $out = (float) ($row->outflow_amount ?? 0);

        return [
            'total_inflow_amount'  => $in,
            'inflow_count'         => (int) ($row->inflow_count ?? 0),
            'total_outflow_amount' => $out,
            'outflow_count'        => (int) ($row->outflow_count ?? 0),
            'balance'              => $in - $out,
        ];
    }


    // public function allFinanceTransactionGroups($request)
    // {
    //     // Validate request parameters
    //     $validated = $request->validate([
    //         'q' => 'nullable|string|max:255',
    //         'start_date' => 'nullable|date_format:Y-m-d',
    //         'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
    //         'type' => 'nullable|string',
    //         'sort_by' => 'nullable|in:alphabetically,date_ascending,date_descending',
    //         'status' => 'nullable|string',
    //         'export' => 'nullable|boolean',
    //     ]);

    //     $query = FinanceAccountTransactionGroup::query()
    //         ->with(['editedBy:id,name'])
    //         ->where('company_id', auth()->user()->current_company_id)
    //         ->whereHas('financeAccountTransactions', function ($query) use ($request) {
    //             $query->where('transactionID', 'LIKE', '%' . $request->q . '%')
    //                 ->orWhereHas('account', function ($query) use ($request) {
    //                     $query->where('name', 'LIKE', '%' . $request->q . '%');
    //                 });
    //         })
    //         ->when($request->start_date && $request->end_date, function ($q) use ($request) {
    //             $q->whereBetween('created_at', [
    //                 \Carbon\Carbon::parse($request->start_date)->startOfDay(),
    //                 \Carbon\Carbon::parse($request->end_date)->endOfDay(),
    //             ]);
    //         })
    //         ->when($request->type, function ($query) use ($request) {
    //             $query->where('payment_type', $request->type);
    //         })
    //         ->when($request->sort_by, function ($query) use ($request) {
    //             if ($request->sort_by === 'alphabetically') {
    //                 return $query->orderBy('name', 'asc');
    //             } elseif ($request->sort_by === 'date_ascending') {
    //                 return $query->orderBy('created_at', 'asc');
    //             } elseif ($request->sort_by === 'date_descending') {
    //                 return $query->orderBy('created_at', 'desc');
    //             }
    //         })
    //         ->when($request->status, function ($query) use ($request) {
    //             $query->where('status', $request->status);
    //         })
    //         ->orderBy('created_at', 'DESC');

    //     // Add subquery to fetch distinct bank names
    //     $query->addSelect([
    //         'finance_account_transaction_groups.*',
    //         \DB::raw(
    //             "
    //         (
    //             SELECT GROUP_CONCAT(DISTINCT finance_chart_of_accounts.name SEPARATOR ', ')
    //             FROM finance_account_transactions
    //             JOIN finance_chart_of_accounts ON finance_account_transactions.account_id = finance_chart_of_accounts.id
    //             WHERE finance_account_transactions.trans_group_id = finance_account_transaction_groups.id
    //         ) as banks_involved"
    //         ),
    //     ]);

    //     $records = $request->export ? $query->get() : $query->paginate(10);

    //     // Transform the records
    //     $records->transform(function ($transactionGroup) {
    //         // Since we removed financeAccountTransactions, we can't calculate inflow/outflow here
    //         // If these fields are still needed, you'll need to fetch them via a separate query or store them in the database
    //         $transactionGroup->inflow_amount = 0; // Placeholder, adjust as needed
    //         $transactionGroup->outflow_amount = 0; // Placeholder, adjust as needed
    //         $transactionGroup->total_value = 0; // Placeholder, adjust as needed

    //         // Use the banks_involved from the subquery
    //         $transactionGroup->banks = $transactionGroup->banks_involved ?: 'N/A';

    //         // Find main bank account (optional, if still needed)
    //         $transactionGroup->bank = $transactionGroup->banks_involved ? explode(', ', $transactionGroup->banks_involved)[0] : 'N/A';

    //         return $transactionGroup;
    //     });

    //     if ($request->export) {
    //         $exportData = $records->map(function ($record) {
    //             return [
    //                 'Transaction ID' => $record->name,
    //                 'Date' => $record->created_at->format('Y-m-d'),
    //                 'Total Value' => $record->total_value,
    //                 'Inflow Amount' => $record->inflow_amount,
    //                 'Outflow Amount' => $record->outflow_amount,
    //                 'Banks Involved' => $record->banks_involved ?: 'N/A',
    //                 'Payment Type' => $record->payment_type,
    //                 'Status' => $record->status,
    //             ];
    //         });

    //         return Excel::download(new FinanceTransactionGroupExport($exportData), 'transactions.xlsx');
    //     }

    //     return $records;
    // }


    //For allFinanceTransactionGroups you should only aggregate the main bank side
    public function allFinanceTransactionGroups($request)
    {
        // 1) Validate
        $validated = $request->validate([
            'q'          => 'nullable|string|max:255',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date'   => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'type'       => 'nullable|string',
            'sort_by'    => 'nullable|in:alphabetically,date_ascending,date_descending',
            'status'     => 'nullable|string',
            'export'     => 'nullable|boolean',
        ]);

        // 2) Base query
        $query = FinanceAccountTransactionGroup::query()
            ->with(['editedBy:id,name'])
            ->where('company_id', auth()->user()->current_company_id)
            // Search only when q is present
            ->when($request->filled('q'), function ($q) use ($request) {
                $q->whereHas('financeAccountTransactions', function ($qq) use ($request) {
                    $qq->where('transactionID', 'LIKE', '%' . $request->q . '%')
                        ->orWhereHas('account', function ($qx) use ($request) {
                            $qx->where('name', 'LIKE', '%' . $request->q . '%');
                        });
                });
            })
            ->when($request->start_date && $request->end_date, function ($q) use ($request) {
                $q->whereBetween('created_at', [
                    \Carbon\Carbon::parse($request->start_date)->startOfDay(),
                    \Carbon\Carbon::parse($request->end_date)->endOfDay(),
                ]);
            })
            ->when($request->type, function ($q) use ($request) {
                $q->where('payment_type', $request->type);
            })
            ->when($request->sort_by, function ($q) use ($request) {
                return match ($request->sort_by) {
                    'alphabetically'   => $q->orderBy('name', 'asc'),
                    'date_ascending'   => $q->orderBy('created_at', 'asc'),
                    'date_descending'  => $q->orderBy('created_at', 'desc'),
                    default            => $q,
                };
            })
            ->when($request->status, function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->orderBy('created_at', 'DESC');

        // 3) Aggregates (computed only from the MAIN BANK side to avoid double-counting)
        // inflow = SUM(mainBank = true AND type = 'Income')
        // outflow = SUM(mainBank = true AND type = 'Expense')
        $query
            ->withSum([
                'financeAccountTransactions as inflow_amount' => function ($q) {
                    $q->where('mainBank', 'true')->where('type', 'Income');
                }
            ], 'amount')
            ->withSum([
                'financeAccountTransactions as outflow_amount' => function ($q) {
                    $q->where('mainBank', 'true')->where('type', 'Expense');
                }
            ], 'amount')
            // net (total_value) = inflow - outflow
            ->addSelect([
                'total_value' => \App\Models\FinanceAccountTransaction::selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN mainBank = 'true' AND type = 'Income' THEN amount
                        WHEN mainBank = 'true' AND type = 'Expense' THEN -amount
                        ELSE 0
                    END
                ), 0)
            ")->whereColumn('finance_account_transactions.trans_group_id', 'finance_account_transaction_groups.id')
            ]);

        // 4) Banks involved (distinct names)
        $query->addSelect([
            'finance_account_transaction_groups.*',
            \DB::raw("
            (
                SELECT GROUP_CONCAT(DISTINCT fcoa.name SEPARATOR ', ')
                FROM finance_account_transactions fat
                JOIN finance_chart_of_accounts fcoa ON fat.account_id = fcoa.id
                WHERE fat.trans_group_id = finance_account_transaction_groups.id
            ) AS banks_involved
        "),
        ]);

        // 5) Fetch (paginate or export)
        $records = $request->boolean('export') ? $query->get() : $query->paginate(10);

        // 6) Final mapping without clobbering computed sums
        $mapFn = function ($tg) {
            // Normalize numeric fields (Laravel returns null if no rows matched)
            $tg->inflow_amount  = (float) ($tg->inflow_amount ?? 0);
            $tg->outflow_amount = (float) ($tg->outflow_amount ?? 0);
            // total_value already selected in SQL; fall back to inflow-outflow if missing
            $tg->total_value    = (float) ($tg->total_value ?? ($tg->inflow_amount - $tg->outflow_amount));
            // Optional convenience: total_amount = absolute flow (in + out)
            $tg->total_amount   = $tg->inflow_amount + $tg->outflow_amount;

            // Banks display helpers
            $tg->banks = $tg->banks_involved ?: 'N/A';
            $tg->bank  = $tg->banks_involved ? explode(', ', $tg->banks_involved)[0] : 'N/A';

            return $tg;
        };

        if ($records instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $records->setCollection($records->getCollection()->map($mapFn));
        } else {
            // Collection (export path)
            $records = $records->map($mapFn);
        }

        // 7) Export
        if ($request->boolean('export')) {
            $exportData = $records->map(function ($record) {
                return [
                    'Transaction ID' => $record->name,
                    'Date'           => $record->created_at->format('Y-m-d'),
                    'Total Value'    => $record->total_value,
                    'Inflow Amount'  => $record->inflow_amount,
                    'Outflow Amount' => $record->outflow_amount,
                    'Banks Involved' => $record->banks_involved ?: 'N/A',
                    'Payment Type'   => $record->payment_type,
                    'Status'         => $record->status,
                ];
            });

            return Excel::download(new FinanceTransactionGroupExport($exportData), 'transactions.xlsx');
        }

        return $records;
    }



    public function getTransactionList($request)
    {
        $query = FinanceAccountTransaction::query()
            ->with('account:id,name')
            ->whereHas('financeTransactionGroup', function ($q) use ($request) {
                $q->where('company_id', auth()->user()->current_company_id);

                if ($request->status) {
                    $q->where('status', $request->status);
                }
            })
            ->when($request->q, function ($q) use ($request) {
                $q->where(function ($inner) use ($request) {
                    $inner->where('transactionID', 'LIKE', '%' . $request->q . '%')
                        ->orWhere('referenceID', 'LIKE', '%' . $request->q . '%');
                });
            })
            ->when($request->start_date && $request->end_date, function ($q) use ($request) {
                $q->whereBetween('created_at', [
                    $request->start_date . ' 00:00:00',
                    $request->end_date . ' 23:59:59'
                ]);
            });

        // Clone for raw grouping and aggregation
        $grouped = (clone $query)
            ->select(
                'trans_group_id',
                DB::raw('DATE(transaction_date) as date'),
                DB::raw('SUM(amount) as total_value'),
                DB::raw('SUM(CASE WHEN type = "Income" THEN amount ELSE 0 END) as inflow_amount'),
                DB::raw('COUNT(CASE WHEN type = "Income" THEN 1 ELSE NULL END) as inflow_count'),
                DB::raw('SUM(CASE WHEN type = "Expense" THEN amount ELSE 0 END) as outflow_amount'),
                DB::raw('COUNT(CASE WHEN type = "Expense" THEN 1 ELSE NULL END) as outflow_count')
            )
            ->groupBy('trans_group_id', DB::raw('DATE(transaction_date)'))
            ->orderBy('date', 'desc');

        // Handle export
        if ($request->export) {
            $records = $grouped->get()->map(function ($record) {
                $banks = FinanceAccountTransaction::where('trans_group_id', $record->trans_group_id)
                    ->with('account:id,name')
                    ->get()
                    ->pluck('account.name', 'account.id')
                    ->unique()
                    ->map(function ($name, $id) {
                        return ['id' => $id, 'name' => $name];
                    })
                    ->values();

                return (object) [
                    'trans_group_id' => $record->trans_group_id,
                    'date' => $record->date,
                    'total_value' => $record->total_value,
                    'inflow_amount' => $record->inflow_amount,
                    'inflow_count' => $record->inflow_count,
                    'outflow_amount' => $record->outflow_amount,
                    'outflow_count' => $record->outflow_count,
                    'banks_involved' => $banks
                ];
            });
            return Excel::download(new TransactionExport($records), 'transactions.xlsx');
        }

        // Paginated or full results
        $records = $request->paginate
            ? $grouped->paginate($request->get('per_page', 10))
            : $grouped->get();

        // Add banks involved while preserving pagination
        if ($request->paginate) {
            $records->transform(function ($record) {
                $banks = FinanceAccountTransaction::where('trans_group_id', $record->trans_group_id)
                    ->with('account:id,name')
                    ->get()
                    ->pluck('account.name', 'account.id')
                    ->unique()
                    ->map(function ($name, $id) {
                        return ['id' => $id, 'name' => $name];
                    })
                    ->values();

                return (object) [
                    'trans_group_id' => $record->trans_group_id,
                    'date' => $record->date,
                    'total_value' => $record->total_value,
                    'inflow_amount' => $record->inflow_amount,
                    'inflow_count' => $record->inflow_count,
                    'outflow_amount' => $record->outflow_amount,
                    'outflow_count' => $record->outflow_count,
                    'banks_involved' => $banks
                ];
            });
        } else {
            $records = $records->map(function ($record) {
                $banks = FinanceAccountTransaction::where('trans_group_id', $record->trans_group_id)
                    ->with('account:id,name')
                    ->get()
                    ->pluck('account.name', 'account.id')
                    ->unique()
                    ->map(function ($name, $id) {
                        return ['id' => $id, 'name' => $name];
                    })
                    ->values();

                return (object) [
                    'trans_group_id' => $record->trans_group_id,
                    'date' => $record->date,
                    'total_value' => $record->total_value,
                    'inflow_amount' => $record->inflow_amount,
                    'inflow_count' => $record->inflow_count,
                    'outflow_amount' => $record->outflow_amount,
                    'outflow_count' => $record->outflow_count,
                    'banks_involved' => $banks
                ];
            });
        }

        return $records;
    }


    public function createFinanceTransaction($request)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();

            $journal_date = date('Y-m-d');
            $publishedStatus = $request->status ?? "published"; //'draft', 'pending', 'published', 'unpublished'

            //create journal entry
            $journalEntry = FinanceJournalEntry::create([
                'date' => $journal_date,
                'info' => 'FinanceAccountTransaction',
                'edited_by' => $user->id,
                'company_id' => auth()->user()->current_company_id,
                'status' => $publishedStatus, //draft. pending, published
            ]);

            $uniqueId = GeneralHelper::getModelUniqueRandomId2([
                "modelNamespace" => 'App\Models\FinanceAccountTransactionGroup',
                "modelField" => 'name',
                "prefix" => 'FTR-',
                "idLength" => 7,
                "idType" => "numalpha"
            ]);


            //create transaction group
            $transactionGroup = FinanceAccountTransactionGroup::create([
                'name' => $uniqueId,
                'payment_type' => $request->payment_type, //'payment', 'receipt'
                'status' => $publishedStatus, //draft. pending, published
                'edited_by' => $user->id,
                'company_id' => auth()->user()->current_company_id,
                'journal_entry_id' => $journalEntry->id,
            ]);

            $mainBank = $request->bank_account_id;

            foreach ($request->financeTransactions as $key => $financeTransaction) {
                $amount = $financeTransaction['amount'];
                $date = $financeTransaction['transaction_date'];
                // $fromAccount = $financeTransaction['type'] == "Debit" ? $financeTransaction['account_id'] : $mainBank;
                // $toAccount = $financeTransaction['type'] == "Credit" ? $financeTransaction['account_id'] : $mainBank;
                $fromAccount = $financeTransaction['type'] == "Expense" ? $financeTransaction['account_id'] : $mainBank;
                $toAccount = $financeTransaction['type'] == "Income" ? $financeTransaction['account_id'] : $mainBank;


                $createTransactions = FinanceAccountTransaction::create([
                    'trans_group_id' => $transactionGroup->id,
                    'journal_entry_id' => $journalEntry->id,
                    'transaction_date' => $financeTransaction['transaction_date'],
                    'account_id' => $financeTransaction['account_id'],
                    'transactionID' => $financeTransaction['transactionID'],
                    'referenceID' => $financeTransaction['referenceID'],
                    'description' => $financeTransaction['description'],
                    'type' => $financeTransaction['type'] == "Income" ? "Expense" : "Income", //expense, income //AccountingDebitAndCredit
                    'category' => $financeTransaction['type'] == "Income" ? "Other" : "Deposit", //AccountingDebitAndCredit
                    // 'type' => $financeTransaction['type'] == "Credit" ? "Expense" : "Income", //expense, income //AccountingDebitAndCredit
                    // 'category' => $financeTransaction['type'] == "Credit" ? "Other" : "Deposit", //AccountingDebitAndCredit
                    'amount' => $financeTransaction['amount'],
                    'mode_of_payment' => $financeTransaction['mode_of_payment'], //Bank Transfer, Cash, Credit/Debit Card, Cheque
                    'mainBank' => "false",
                    'edited_by' => $user->id,

                    'payment_type' => $request->payment_type,
                    'bank_fee' => $financeTransaction['bank_fee'],
                    'exchange_rate' => $financeTransaction['exchange_rate'],
                ]);

                //
                $createTransactions2 = FinanceAccountTransaction::create([
                    'trans_group_id' => $transactionGroup->id,
                    'journal_entry_id' => $journalEntry->id,
                    'transaction_date' => $financeTransaction['transaction_date'],
                    'account_id' => $mainBank,
                    'transactionID' => $financeTransaction['transactionID'],
                    'referenceID' => $financeTransaction['referenceID'],
                    'description' => $financeTransaction['description'],
                    //type and category opposite of first transaction
                    'type' => $financeTransaction['type'] == "Income"  ? "Income" : "Expense", //expense, income //AccountingDebitAndCredit
                    'category' => $financeTransaction['type'] == "Income" ? "Deposit" : "Other", //AccountingDebitAndCredit
                    // 'type' => $financeTransaction['type'] == "Credit"  ? "Income" : "Expense", //expense, income //AccountingDebitAndCredit
                    // 'category' => $financeTransaction['type'] == "Credit" ? "Deposit" : "Other", //AccountingDebitAndCredit
                    'amount' => $financeTransaction['amount'],
                    'mode_of_payment' => $financeTransaction['mode_of_payment'], //Bank Transfer, Cash, Credit/Debit Card, Cheque
                    'mainBank' => "true",
                    'edited_by' => $user->id,

                    'payment_type' => $request->payment_type,
                    'bank_fee' => $financeTransaction['bank_fee'],
                    'exchange_rate' => $financeTransaction['exchange_rate'],
                ]);

                // doubleEntry($journalEntryID, $date, $debitAccountID, $creditAccountID, $amount, $description, $reference, $profitCenter)
                if ($request->status == "published") {
                    $accountEntries = AccountEntriesDoubleEntryHelper::doubleEntry($journalEntry->id, $date, $fromAccount, $toAccount, $amount, $financeTransaction['description'], $financeTransaction['referenceID'], $financeTransaction['bank_fee'], $financeTransaction['exchange_rate']);
                    if ($accountEntries != null) {
                        throw new \Exception("Error recording account entries: " . $accountEntries);
                    }
                }
            }


            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $transactionGroup->id,
                'action_type' => "Models\FinanceTransactionGroup",
                'log_name' => "Transaction Group created successfully",
                'description' => "Transaction Group created successfully by {$user->lastname} {$user->firstname}",
            ];

            // AuditLog::storeAuditLog($dataToLog);
            DB::commit();
            return $transactionGroup;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function updateFinanceTransaction($request, int $transactionGroupId)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();

            // Load group & journal, scoped to company
            $transactionGroup = FinanceAccountTransactionGroup::query()
                ->where('id', $transactionGroupId)
                ->where('company_id', $user->current_company_id)
                ->firstOrFail();

            $journalEntry = FinanceJournalEntry::query()
                ->where('id', $transactionGroup->journal_entry_id)
                ->where('company_id', $user->current_company_id)
                ->firstOrFail();

            // Status to use (keep existing if not provided)
            $status = $request->status ?? $transactionGroup->status;

            // Update journal & group
            $journalEntry->update([
                'date'      => date('Y-m-d'),
                'status'    => $status,
                'edited_by' => $user->id,
            ]);

            $transactionGroup->update([
                'payment_type' => $request->payment_type,
                'status'       => $status,
                'edited_by'    => $user->id,
            ]);

            // 🔥 Delete existing account entries and transactions (use RELATION METHODS)
            $journalEntry->accountEntries()->delete();
            $transactionGroup->financeAccountTransactions()->delete();

            // Validate incoming essentials
            $mainBank = $request->bank_account_id;
            if (!$mainBank) {
                throw new \Exception('bank_account_id is required.');
            }

            $rows = $request->financeTransactions ?? [];
            if (!is_array($rows) || count($rows) === 0) {
                throw new \Exception('financeTransactions must be a non-empty array.');
            }

            foreach ($rows as $tx) {
                // ---- Normalize type: support Credit/Debit and Income/Expense ----
                $rawType = $tx['type'] ?? null; // 'Credit'|'Debit' or 'Income'|'Expense'
                if (!$rawType) {
                    throw new \Exception('Each transaction requires a type.');
                }

                $kind = match (strtolower($rawType)) {
                    'credit', 'income'  => 'Income',
                    'debit',  'expense' => 'Expense',
                    default             => throw new \Exception("Unsupported transaction type: {$rawType}")
                };
                // ----------------------------------------------------------------

                $amount     = $tx['amount'] ?? 0;
                $date       = $tx['transaction_date'] ?? date('Y-m-d');
                $accountId  = $tx['account_id'] ?? null;
                if (!$accountId) {
                    throw new \Exception('Each transaction requires an account_id.');
                }

                // Mirror your create() mapping for double-entry direction:
                // Expense  => from = expense account, to = main bank
                // Income   => from = main bank,       to = income account
                $fromAccount = $kind === 'Expense' ? $accountId : $mainBank;
                $toAccount   = $kind === 'Income'  ? $accountId : $mainBank;

                // --- Non-bank side (opposite type/category) ---
                FinanceAccountTransaction::create([
                    'trans_group_id'    => $transactionGroup->id,
                    'journal_entry_id'  => $journalEntry->id,
                    'transaction_date'  => $date,
                    'account_id'        => $accountId,
                    'transactionID'     => $tx['transactionID'] ?? null,
                    'referenceID'       => $tx['referenceID'] ?? null,
                    'description'       => $tx['description'] ?? null,
                    'type'              => $kind === 'Income' ? 'Expense' : 'Income',
                    'category'          => $kind === 'Income' ? 'Other'   : 'Deposit',
                    'amount'            => $amount,
                    'mode_of_payment'   => $tx['mode_of_payment'] ?? null,
                    'mainBank'          => 'false',
                    'edited_by'         => $user->id,
                    'payment_type'      => $request->payment_type,
                    'bank_fee'          => $tx['bank_fee'] ?? null,
                    'exchange_rate'     => $tx['exchange_rate'] ?? null,
                ]);

                // --- Main bank side (same type/category as logical kind) ---
                FinanceAccountTransaction::create([
                    'trans_group_id'    => $transactionGroup->id,
                    'journal_entry_id'  => $journalEntry->id,
                    'transaction_date'  => $date,
                    'account_id'        => $mainBank,
                    'transactionID'     => $tx['transactionID'] ?? null,
                    'referenceID'       => $tx['referenceID'] ?? null,
                    'description'       => $tx['description'] ?? null,
                    'type'              => $kind, // Income or Expense
                    'category'          => $kind === 'Income' ? 'Deposit' : 'Other',
                    'amount'            => $amount,
                    'mode_of_payment'   => $tx['mode_of_payment'] ?? null,
                    'mainBank'          => 'true',
                    'edited_by'         => $user->id,
                    'payment_type'      => $request->payment_type,
                    'bank_fee'          => $tx['bank_fee'] ?? null,
                    'exchange_rate'     => $tx['exchange_rate'] ?? null,
                ]);

                // Double-entry postings (only when published)
                if ($status === 'published') {
                    $err = AccountEntriesDoubleEntryHelper::doubleEntry(
                        $journalEntry->id,
                        $date,
                        $fromAccount,
                        $toAccount,
                        $amount,
                        $tx['description'] ?? null,
                        $tx['referenceID'] ?? null,
                        $tx['bank_fee'] ?? null,
                        $tx['exchange_rate'] ?? null
                    );

                    if ($err !== null) {
                        throw new \Exception("Error recording account entries: " . $err);
                    }
                }
            }

            // (Optional) Audit log here...

            DB::commit();

            // Return fresh instance (with any lazy props updated)
            return $transactionGroup->refresh();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }


    // public function updateFinanceTransaction($request, $transactionGroupId)
    // {
    //     DB::beginTransaction();
    //     try {
    //         $user = auth()->user();

    //         // Find the transaction group
    //         $transactionGroup = FinanceAccountTransactionGroup::where('id', $transactionGroupId)
    //             ->where('company_id', $user->current_company_id)
    //             ->firstOrFail();

    //         // Find the associated journal entry
    //         $journalEntry = FinanceJournalEntry::where('id', $transactionGroup->journal_entry_id)
    //             ->where('company_id', $user->current_company_id)
    //             ->firstOrFail();

    //         // Update journal entry
    //         $journalEntry->update([
    //             'date' => date('Y-m-d'),
    //             'status' => $request->status ?? $journalEntry->status,
    //             'edited_by' => $user->id,
    //         ]);

    //         // Update transaction group
    //         $transactionGroup->update([
    //             'payment_type' => $request->payment_type,
    //             'status' => $request->status ?? $transactionGroup->status,
    //             'edited_by' => $user->id,
    //         ]);

    //         // Delete existing transactions and account entries
    //         $existingTransactions = FinanceAccountTransaction::where('trans_group_id', $transactionGroup->id)->get();
    //         if ($existingTransactions->isEmpty()) {
    //             throw new \Exception('No transactions found for this transaction group.');
    //         }

    //         $journalEntryId = $existingTransactions->first()->journal_entry_id;
    //         if ($journalEntryId) {
    //             $journalEntryInfo = FinanceJournalEntry::find($journalEntryId);
    //             optional($journalEntryInfo->accountEntries())->delete(); // Delete associated account entries
    //             $transactionGroup->financeAccountTransactions()->delete();
    //             // FinanceAccountTransaction::where('trans_group_id', $transactionGroup->id)->delete();
    //         }

    //         // Create new transactions
    //         $mainBank = $request->bank_account_id;
    //         foreach ($request->financeTransactions as $financeTransaction) {
    //             $amount = $financeTransaction['amount'];
    //             $date = $financeTransaction['transaction_date'];
    //             $fromAccount = $financeTransaction['type'] === 'Debit' ? $financeTransaction['account_id'] : $mainBank;
    //             $toAccount = $financeTransaction['type'] === 'Credit' ? $financeTransaction['account_id'] : $mainBank;

    //             // Create new transaction
    //             $newTransaction = FinanceAccountTransaction::create([
    //                 'trans_group_id' => $transactionGroup->id,
    //                 'journal_entry_id' => $journalEntry->id,
    //                 'transaction_date' => $financeTransaction['transaction_date'],
    //                 'account_id' => $financeTransaction['account_id'],
    //                 'transactionID' => $financeTransaction['transactionID'],
    //                 'referenceID' => $financeTransaction['referenceID'],
    //                 'description' => $financeTransaction['description'],
    //                 'type' => $financeTransaction['type'] === 'Credit' ? 'Expense' : 'Income',
    //                 'category' => $financeTransaction['type'] === 'Credit' ? 'Other' : 'Deposit',
    //                 'amount' => $financeTransaction['amount'],
    //                 'mode_of_payment' => $financeTransaction['mode_of_payment'],
    //                 'mainBank' => 'false',
    //                 'edited_by' => $user->id,
    //                 'payment_type' => $request->payment_type,
    //                 'bank_fee' => $financeTransaction['bank_fee'],
    //                 'exchange_rate' => $financeTransaction['exchange_rate'],
    //             ]);

    //             // Create paired transaction (main bank)
    //             $newPairedTransaction = FinanceAccountTransaction::create([
    //                 'trans_group_id' => $transactionGroup->id,
    //                 'journal_entry_id' => $journalEntry->id,
    //                 'transaction_date' => $financeTransaction['transaction_date'],
    //                 'account_id' => $mainBank,
    //                 'transactionID' => $financeTransaction['transactionID'],
    //                 'referenceID' => $financeTransaction['referenceID'],
    //                 'description' => $financeTransaction['description'],
    //                 'type' => $financeTransaction['type'] === 'Credit' ? 'Income' : 'Expense',
    //                 'category' => $financeTransaction['type'] === 'Credit' ? 'Deposit' : 'Other',
    //                 'amount' => $financeTransaction['amount'],
    //                 'mode_of_payment' => $financeTransaction['mode_of_payment'],
    //                 'mainBank' => 'true',
    //                 'edited_by' => $user->id,
    //                 'payment_type' => $request->payment_type,
    //                 'bank_fee' => $financeTransaction['bank_fee'],
    //                 'exchange_rate' => $financeTransaction['exchange_rate'],
    //             ]);

    //             // Update double-entry accounting if published
    //             if ($request->status === 'published') {
    //                 $accountEntries = AccountEntriesDoubleEntryHelper::doubleEntry(
    //                     $journalEntry->id,
    //                     $date,
    //                     $fromAccount,
    //                     $toAccount,
    //                     $amount,
    //                     $financeTransaction['description'],
    //                     $financeTransaction['referenceID'],
    //                     $financeTransaction['bank_fee'],
    //                     $financeTransaction['exchange_rate']
    //                 );
    //                 if ($accountEntries !== null) {
    //                     throw new \Exception("Error recording account entries: " . $accountEntries);
    //                 }
    //             }
    //         }

    //         // Log the update
    //         $dataToLog = [
    //             'causer_id' => $user->id,
    //             'action_id' => $transactionGroup->id,
    //             'action_type' => 'Models\FinanceAccountTransactionGroup',
    //             'log_name' => 'Transaction Group updated successfully',
    //             'description' => "Transaction Group updated successfully by {$user->lastname} {$user->firstname}",
    //         ];

    //         // AuditLog::storeAuditLog($dataToLog);

    //         DB::commit();
    //         return $transactionGroup;
    //     } catch (ValidationException $e) {
    //         DB::rollBack();
    //         throw $e;
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         throw $th;
    //     }
    // }

    public function viewFinanceTransactionGroup($id)
    {
        $financeTransactionGroup = FinanceAccountTransactionGroup::query()
            ->with([
                'financeAccountTransactions.account', // Eager load the account relationship
                'editedBy:id,name'
            ])
            ->find($id);

        if (!$financeTransactionGroup) {
            return null;
        }

        $all = $financeTransactionGroup->financeAccountTransactions;

        // capture main bank BEFORE transform/remove
        $mainBankRow = $all->firstWhere('mainBank', 'true');

        $cleaned = $all
            ->reject(fn($tx) => $tx->mainBank === 'true') // drop main-bank rows
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'account_id' => $transaction->account_id,
                    'account_name' => optional($transaction->account)->name ?? 'N/A',
                    'trans_group_id' => $transaction->trans_group_id,
                    'journal_entry_id' => $transaction->journal_entry_id,
                    'transaction_date' => $transaction->transaction_date,
                    'transactionID' => $transaction->transactionID,
                    'referenceID' => $transaction->referenceID,
                    'mainBank' => $transaction->mainBank,
                    'type' => $transaction->type,
                    'payment_type' => $transaction->payment_type,
                    'category' => $transaction->category,
                    'amount' => $transaction->amount,
                    'description' => $transaction->description,
                    'mode_of_payment' => $transaction->mode_of_payment,
                    'exchange_rate' => $transaction->exchange_rate,
                    'bank_fee' => $transaction->bank_fee,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                ];
            })
            ->values(); // ✅ reindex to avoid gaps/nulls in the array

        $mainBank = $mainBankRow
            ? ['id' => $mainBankRow->account_id, 'name' => optional($mainBankRow->account)->name ?? 'N/A']
            : null;

        // // Transform the financeAccountTransactions to include account_id and account_name, excluding mainBank = true
        // $financeTransactionGroup->financeAccountTransactions->transform(function ($transaction) {
        //     if ($transaction->mainBank === 'true') {
        //         return null; // Skip transactions where mainBank is true
        //     }
        //     return [
        //         'id' => $transaction->id,
        //         'account_id' => $transaction->account_id,
        //         'account_name' => $transaction->account ? $transaction->account->name : 'N/A',
        //         'trans_group_id' => $transaction->trans_group_id,
        //         'journal_entry_id' => $transaction->journal_entry_id,
        //         'transaction_date' => $transaction->transaction_date,
        //         'transactionID' => $transaction->transactionID,
        //         'referenceID' => $transaction->referenceID,
        //         'mainBank' => $transaction->mainBank,
        //         'type' => $transaction->type,
        //         'payment_type' => $transaction->payment_type,
        //         'category' => $transaction->category,
        //         'amount' => $transaction->amount,
        //         'description' => $transaction->description,
        //         'mode_of_payment' => $transaction->mode_of_payment,
        //         'exchange_rate' => $transaction->exchange_rate,
        //         'bank_fee' => $transaction->bank_fee,
        //         'created_at' => $transaction->created_at,
        //         'updated_at' => $transaction->updated_at,
        //         // 'deleted_at' => $transaction->deleted_at,
        //     ];
        // })->filter(); // Remove null entries from the collection

        // // Find the main bank transaction
        // $mainBankTransaction = $financeTransactionGroup->financeAccountTransactions->firstWhere('mainBank', 'true');


        // Prepare the response array
        return [
            'main_bank' => $mainBank,
            'id' => $financeTransactionGroup->id,
            'edited_by' => $financeTransactionGroup->editedBy ? [
                'id' => $financeTransactionGroup->editedBy->id,
                'name' => $financeTransactionGroup->editedBy->name
            ] : null,
            'name' => $financeTransactionGroup->name,
            'payment_type' => $financeTransactionGroup->payment_type,
            'status' => $financeTransactionGroup->status,
            'created_at' => $financeTransactionGroup->created_at,
            'updated_at' => $financeTransactionGroup->updated_at,
            // 'deleted_at' => $financeTransactionGroup->deleted_at,
            'company_id' => $financeTransactionGroup->company_id,
            'journal_entry_id' => $financeTransactionGroup->journal_entry_id,
            'finance_account_transactions' => $cleaned->toArray(),
            // 'finance_account_transactions' => $financeTransactionGroup->financeAccountTransactions->toArray(),
        ];
    }
}
