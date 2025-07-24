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

    public function getTransactionOverview($request)
    {
        $query = FinanceAccountTransaction::query();

        if ($request->filled('start_date')) {
            $query->whereDate('transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('transaction_date', '<=', $request->end_date);
        }

        $inflowAmount = (clone $query)->where('type', 'Income')->sum('amount');
        $inflowCount = (clone $query)->where('type', 'Income')->count();

        $outflowAmount = (clone $query)->where('type', 'Expense')->sum('amount');
        $outflowCount = (clone $query)->where('type', 'Expense')->count();

        return [
            'total_inflow_amount' => $inflowAmount,
            'inflow_count' => $inflowCount,
            'total_outflow_amount' => $outflowAmount,
            'outflow_count' => $outflowCount,
            'balance' => $inflowAmount - $outflowAmount,
        ];
    }

    public function allFinanceTransactionGroups($request)
    {
        // Validate request parameters
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'type' => 'nullable|string',
            'sort_by' => 'nullable|in:alphabetically,date_ascending,date_descending',
            'status' => 'nullable|string',
            'export' => 'nullable|boolean',
        ]);

        $query = FinanceAccountTransactionGroup::query()
            ->with(['financeAccountTransactions', 'editedBy:id,name'])
            ->where('company_id', auth()->user()->current_company_id)
            ->whereHas('financeAccountTransactions', function ($query) use ($request) {
                $query->where('transactionID', 'LIKE', '%' . $request->q . '%')
                    ->orWhereHas('account', function ($query) use ($request) {
                        $query->where('name', 'LIKE', '%' . $request->q . '%');
                    });
            })
            ->when($request->start_date && $request->end_date, function ($q) use ($request) {
                $q->whereBetween('created_at', [
                    \Carbon\Carbon::parse($request->start_date)->startOfDay(),
                    \Carbon\Carbon::parse($request->end_date)->endOfDay(),
                ]);
            })
            ->when($request->type, function ($query) use ($request) {
                $query->where('payment_type', $request->type);
            })
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by === 'alphabetically') {
                    return $query->orderBy('name', 'asc');
                } elseif ($request->sort_by === 'date_ascending') {
                    return $query->orderBy('created_at', 'asc');
                } elseif ($request->sort_by === 'date_descending') {
                    return $query->orderBy('created_at', 'desc');
                }
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->orderBy('created_at', 'DESC');

        $records = $request->export ? $query->get() : $query->paginate(10);

        // Transform the records
        $records->transform(function ($transactionGroup) {
            // Calculate totals
            $inflowAmount = $transactionGroup->financeAccountTransactions
                ->where('type', 'Income')
                ->sum('amount');

            $outflowAmount = $transactionGroup->financeAccountTransactions
                ->where('type', 'Expense')
                ->sum('amount');

            $transactionGroup->inflow_amount = $inflowAmount ?? 0;
            $transactionGroup->outflow_amount = $outflowAmount ?? 0;
            $transactionGroup->total_value = $inflowAmount - $outflowAmount;

            // Find main bank account
            $mainBankTransaction = $transactionGroup->financeAccountTransactions->first(function ($transaction) {
                return $transaction->mainBank === true || $transaction->mainBank === 'true';
            });

            $transactionGroup->bank = $mainBankTransaction && $transactionGroup->account
                ? $mainBankTransaction->account->name
                : 'N/A';

            return $transactionGroup;
        });

        if ($request->export) {
            $exportData = $records->map(function ($record) {
                $banks = $record->financeAccountTransactions
                    ->pluck('account.name')
                    ->unique()
                    ->filter()
                    ->values();

                return [
                    'Transaction ID' => $record->name,
                    'Date' => $record->created_at->format('Y-m-d'),
                    'Total Value' => $record->total_value,
                    'Inflow Amount' => $record->inflow_amount,
                    'Outflow Amount' => $record->outflow_amount,
                    'Banks Involved' => $banks->implode(', '),
                    'Payment Type' => $record->payment_type,
                    'Status' => $record->status,
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
                $fromAccount = $financeTransaction['type'] == "Debit" ? $financeTransaction['account_id'] : $mainBank;
                $toAccount = $financeTransaction['type'] == "Credit" ? $financeTransaction['account_id'] : $mainBank;


                $createTransactions = FinanceAccountTransaction::create([
                    'trans_group_id' => $transactionGroup->id,
                    'journal_entry_id' => $journalEntry->id,
                    'transaction_date' => $financeTransaction['transaction_date'],
                    'account_id' => $financeTransaction['account_id'],
                    'transactionID' => $financeTransaction['transactionID'],
                    'referenceID' => $financeTransaction['referenceID'],
                    'description' => $financeTransaction['description'],
                    'type' => $financeTransaction['type'] == "Credit" ? "Expense" : "Income", //expense, income //AccountingDebitAndCredit
                    'category' => $financeTransaction['type'] == "Credit" ? "Other" : "Deposit", //AccountingDebitAndCredit
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
                    'type' => $financeTransaction['type'] == "Credit"  ? "Income" : "Expense", //expense, income //AccountingDebitAndCredit
                    'category' => $financeTransaction['type'] == "Credit" ? "Deposit" : "Other", //AccountingDebitAndCredit
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

    public function updateFinanceTransaction($request, $transactionGroupId)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();

            // Validate request parameters
            // $validated = $request->validate([
            //     'payment_type' => 'required|string|in:payment,receipt',
            //     'status' => 'nullable|string|in:draft,pending,published,unpublished',
            //     'bank_account_id' => 'required|exists:finance_accounts,id',
            //     'financeTransactions' => 'required|array|min:1',
            //     'financeTransactions.*.transaction_date' => 'required|date_format:Y-m-d',
            //     'financeTransactions.*.account_id' => 'required|exists:finance_accounts,id',
            //     'financeTransactions.*.transactionID' => 'required|string|max:255',
            //     'financeTransactions.*.referenceID' => 'nullable|string|max:255',
            //     'financeTransactions.*.description' => 'nullable|string|max:255',
            //     'financeTransactions.*.type' => 'required|string|in:Debit,Credit',
            //     'financeTransactions.*.amount' => 'required|numeric|min:0',
            //     'financeTransactions.*.mode_of_payment' => 'required|string|in:Bank Transfer,Cash,Credit/Debit Card,Cheque',
            //     'financeTransactions.*.bank_fee' => 'nullable|numeric|min:0',
            //     'financeTransactions.*.exchange_rate' => 'nullable|numeric|min:0',
            // ]);

            // Find the transaction group
            $transactionGroup = FinanceAccountTransactionGroup::where('id', $transactionGroupId)
                ->where('company_id', $user->current_company_id)
                ->firstOrFail();

            // Find the associated journal entry
            $journalEntry = FinanceJournalEntry::where('id', $transactionGroup->journal_entry_id)
                ->where('company_id', $user->current_company_id)
                ->firstOrFail();

            // Update journal entry
            $journalEntry->update([
                'date' => date('Y-m-d'),
                'status' => $request->status ?? $journalEntry->status,
                'edited_by' => $user->id,
            ]);

            // Update transaction group
            $transactionGroup->update([
                'payment_type' => $request->payment_type,
                'status' => $request->status ?? $transactionGroup->status,
                'edited_by' => $user->id,
            ]);

            // Delete existing transactions and account entries
            $existingTransactions = FinanceAccountTransaction::where('trans_group_id', $transactionGroup->id)->get();
            if ($existingTransactions->isEmpty()) {
                throw new \Exception('No transactions found for this transaction group.');
            }

            $journalEntryId = $existingTransactions->first()->journal_entry_id;
            if ($journalEntryId) {
                $journalEntryInfo = FinanceJournalEntry::find($journalEntryId);
                optional($journalEntryInfo->accountEntries())->delete(); // Delete associated account entries
                optional($transactionGroup->financeAccountTransactions())->delete(); // Delete transaction records
            }

            // Create new transactions
            $mainBank = $request->bank_account_id;
            foreach ($request->financeTransactions as $financeTransaction) {
                $amount = $financeTransaction['amount'];
                $date = $financeTransaction['transaction_date'];
                $fromAccount = $financeTransaction['type'] === 'Debit' ? $financeTransaction['account_id'] : $mainBank;
                $toAccount = $financeTransaction['type'] === 'Credit' ? $financeTransaction['account_id'] : $mainBank;

                // Create new transaction
                $newTransaction = FinanceAccountTransaction::create([
                    'trans_group_id' => $transactionGroup->id,
                    'journal_entry_id' => $journalEntry->id,
                    'transaction_date' => $financeTransaction['transaction_date'],
                    'account_id' => $financeTransaction['account_id'],
                    'transactionID' => $financeTransaction['transactionID'],
                    'referenceID' => $financeTransaction['referenceID'],
                    'description' => $financeTransaction['description'],
                    'type' => $financeTransaction['type'] === 'Credit' ? 'Expense' : 'Income',
                    'category' => $financeTransaction['type'] === 'Credit' ? 'Other' : 'Deposit',
                    'amount' => $financeTransaction['amount'],
                    'mode_of_payment' => $financeTransaction['mode_of_payment'],
                    'mainBank' => 'false',
                    'edited_by' => $user->id,
                    'payment_type' => $request->payment_type,
                    'bank_fee' => $financeTransaction['bank_fee'],
                    'exchange_rate' => $financeTransaction['exchange_rate'],
                ]);

                // Create paired transaction (main bank)
                $newPairedTransaction = FinanceAccountTransaction::create([
                    'trans_group_id' => $transactionGroup->id,
                    'journal_entry_id' => $journalEntry->id,
                    'transaction_date' => $financeTransaction['transaction_date'],
                    'account_id' => $mainBank,
                    'transactionID' => $financeTransaction['transactionID'],
                    'referenceID' => $financeTransaction['referenceID'],
                    'description' => $financeTransaction['description'],
                    'type' => $financeTransaction['type'] === 'Credit' ? 'Income' : 'Expense',
                    'category' => $financeTransaction['type'] === 'Credit' ? 'Deposit' : 'Other',
                    'amount' => $financeTransaction['amount'],
                    'mode_of_payment' => $financeTransaction['mode_of_payment'],
                    'mainBank' => 'true',
                    'edited_by' => $user->id,
                    'payment_type' => $request->payment_type,
                    'bank_fee' => $financeTransaction['bank_fee'],
                    'exchange_rate' => $financeTransaction['exchange_rate'],
                ]);

                // Update double-entry accounting if published
                if ($request->status === 'published') {
                    $accountEntries = AccountEntriesDoubleEntryHelper::doubleEntry(
                        $journalEntry->id,
                        $date,
                        $fromAccount,
                        $toAccount,
                        $amount,
                        $financeTransaction['description'],
                        $financeTransaction['referenceID'],
                        $financeTransaction['bank_fee'],
                        $financeTransaction['exchange_rate']
                    );
                    if ($accountEntries !== null) {
                        throw new \Exception("Error recording account entries: " . $accountEntries);
                    }
                }
            }

            // Log the update
            $dataToLog = [
                'causer_id' => $user->id,
                'action_id' => $transactionGroup->id,
                'action_type' => 'Models\FinanceAccountTransactionGroup',
                'log_name' => 'Transaction Group updated successfully',
                'description' => "Transaction Group updated successfully by {$user->lastname} {$user->firstname}",
            ];

            // AuditLog::storeAuditLog($dataToLog);

            DB::commit();
            return $transactionGroup;
        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function viewFinanceTransactionGroup($id)
    {
        $financeTransactionGroup =  FinanceAccountTransactionGroup::query()
            ->with(['financeAccountTransactions', 'editedBy'])->find($id);

        return $financeTransactionGroup;
    }
}
