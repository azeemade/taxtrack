<?php

namespace App\Services\Transaction;

use App\Exceptions\BadRequestException;
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
}
