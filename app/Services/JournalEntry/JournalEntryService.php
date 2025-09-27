<?php

namespace App\Services\JournalEntry;

use App\Exceptions\BadRequestException;
use App\Exports\Accounting\JournalEntry\JournalEntryExport;
use App\Http\Requests\Company\Accounting\JournalEntry\JournalEntryRequest;
use App\Models\FinanceAccountEntry;
use App\Models\FinanceJournalEntry;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class JournalEntryService
{

    public function allJournalEntries($request)
    {
        try {
            $limit = $request->limit ?? 10;
            $sortBy = $request->sort_by;
            $filterBy = $request->filter_by;
            $export = $request->export;
            $carbonDateFilter = $request->date_filter;
            $searchParams = $request->q;
            (!is_null($request->start_date) && !is_null($request->end_date)) ? $dateSearchParams = true : $dateSearchParams = false;

            $record = FinanceJournalEntry::where('company_id',  auth()->user()->current_company_id)
                ->with([
                    'accountEntries' => function ($query) {
                        $query->select(
                            'journal_entry_id',
                            DB::raw('SUM(debit_amount) as total_debit'),
                            DB::raw('SUM(credit_amount) as total_credit')
                        )->groupBy('journal_entry_id');
                    }
                ])
                ->when($searchParams, function ($query) use ($searchParams) {
                    // Option 1: Search amounts if numeric
                    if (is_numeric($searchParams)) {
                        $amount = (float)$searchParams;
                        return $query->whereHas('accountEntries', function ($q) use ($amount) {
                            $q->where('credit_amount', $amount)
                              ->orWhere('debit_amount', $amount);
                        });
                    }
                    
                    // Option 2: Search text fields
                    return $query->whereHas('accountEntries', function ($q) use ($searchParams) {
                        $q->where('reference', 'like', "%{$searchParams}%")
                          ->orWhere('description', 'like', "%{$searchParams}%");
                    });
                })
                ->when($filterBy, function ($query) use ($filterBy) {
                    return $query->where('status', $filterBy);
                })
                ->when($sortBy, function ($query) use ($sortBy) {
                    if ($sortBy === 'alphabetically') {
                        return $query->orderBy('name', 'ASC');
                    } elseif ($sortBy === 'date_descending') {
                        return $query->orderBy('id', 'DESC');
                    } elseif ($sortBy === 'date_ascending') {
                        return $query->orderBy('id', 'ASC');
                    }
                })
                ->when($carbonDateFilter, function ($query) use ($carbonDateFilter) {
                    return $query->where('created_at', '>=', $carbonDateFilter);
                })
                ->when($dateSearchParams, function ($query) use ($request) {
                    $startDate = Carbon::parse($request->start_date);
                    $endDate = Carbon::parse($request->end_date);
                    return $query->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);
                })
                ->where("info", "JournalEntry")
                ->orderBy('created_at', 'DESC');

            // Fetch records
            $record = $export ? $record->get() : $record->paginate($limit);

            if ($export) {
                // Transform collection directly
                $record->transform(function ($entry) {
                    $totalDebit = $entry->accountEntries->sum('total_debit') ?? 0;
                    $totalCredit = $entry->accountEntries->sum('total_credit') ?? 0;
                    unset($entry->accountEntries);
            
                    return array_merge($entry->toArray(), [
                        'total_debit' => $totalDebit,
                        'total_credit' => $totalCredit,
                    ]);
                });

                $fileName = 'journal_entry_report_' . now()->format('Ymd_His') . '.xlsx';

                return Excel::download(
                    new JournalEntryExport($record),
                    $fileName
                );
            }
            
            // Paginated data transform
            $record->getCollection()->transform(function ($entry) {
                $totalDebit = $entry->accountEntries->sum('total_debit') ?? 0;
                $totalCredit = $entry->accountEntries->sum('total_credit') ?? 0;
                unset($entry->accountEntries);
            
                return array_merge($entry->toArray(), [
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                ]);
            });
            return $record;
        } catch (\Throwable $th) {
            throw $th;
        }
    }


    public function createJournalEntry($request)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();

            //create journal entry
            $journalEntry = FinanceJournalEntry::create([
                'date' => now(),
                'info' => 'JournalEntry',
                'edited_by' => $user->id,
                'company_id' => auth()->user()->current_company_id,
                'status' => $request->status, //draft. pending, published
            ]);


            $totalCreditAmount = 0;
            $totalDebitAmount = 0;

            foreach ($request->accountEntries as $key => $accountEntry) {
                $debit_amount = $accountEntry['debit_amount'];
                $credit_amount = $accountEntry['credit_amount'];

                $debit_amount = (float) str_replace(',', '', $debit_amount);
                $credit_amount = (float) str_replace(',', '', $credit_amount);

                $amountValue = ($debit_amount != 0.00) ? $debit_amount : $credit_amount;

                $transactionDate = isset($accountEntry['transaction_date']) && $accountEntry['transaction_date']
                    ? $accountEntry['transaction_date']
                    : $request->journal_date;

                $accountEntry = FinanceAccountEntry::create([
                    'journal_entry_id' => $journalEntry->id,
                    'date' => $request->journal_date,
                    'account_id' => $accountEntry['account_id'],
                    'reference' => $accountEntry['reference'] ?? null,
                    'description' => $accountEntry['description'] ?? null,
                    'debit_amount' => $debit_amount,
                    'credit_amount' => $credit_amount,
                    'amount' => $amountValue,
                    'date' => $transactionDate,
                    'transaction_date' => $transactionDate,
                    'status' => $request->status,
                    'edited_by' => $user->id,
                ]);

                if ($debit_amount) {
                    $totalDebitAmount += $debit_amount;
                } else {
                    $totalCreditAmount += $credit_amount;
                }
            }

            // Check if the credit amount is equal to the debit amount
            if ($totalCreditAmount !== $totalDebitAmount) {
                throw new BadRequestException("Debit amount is not equal to Credit amount.", Response::HTTP_CONFLICT);
            }

            DB::commit();
            return $journalEntry;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function show($id)
    {
        try {
            $record = FinanceJournalEntry::with('accountEntries', 'accountEntries.account:id,name')
                ->where('company_id',  auth()->user()->current_company_id)
                ->where('id', $id)
                ->first();

            if (is_null($record)) {
                throw new BadRequestException("Record not found!", Response::HTTP_NOT_FOUND);
            }

            return $record;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function updateJournalEntry(JournalEntryRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $currentUser = auth()->user();

            $journalEntry = FinanceJournalEntry::where("id", $id)->first();
            if (is_null($journalEntry)) {
                throw new BadRequestException("Record not found!", Response::HTTP_NOT_FOUND);;
            }

            $journalEntry->update([
                'date' => now(),
                'edited_by' => $currentUser->id,
                'status' => $request->status, //draft, pending, published
            ]);


            //delete all account entries for that journal
            $accountEntries = FinanceAccountEntry::where("journal_entry_id", $id)->get();
            if ($accountEntries->isEmpty()) {
                throw new BadRequestException("Record not found!", Response::HTTP_NOT_FOUND);;
            }

            $accountEntries->each(function ($accountEntry) {
                $accountEntry->delete();
            });

            $totalCreditAmount = 0;
            $totalDebitAmount = 0;

            foreach ($request->accountEntries as $key => $accountEntry) {
                $debit_amount = $accountEntry['debit_amount'];
                $credit_amount = $accountEntry['credit_amount'];

                $debit_amount = (float) str_replace(',', '', $debit_amount);
                $credit_amount = (float) str_replace(',', '', $credit_amount);

                $amountValue = ($debit_amount != 0.00) ? $debit_amount : $credit_amount;

                $transactionDate = isset($accountEntry['transaction_date']) && $accountEntry['transaction_date']
                    ? $accountEntry['transaction_date']
                    : $request->journal_date;

                $accountEntry = FinanceAccountEntry::create([
                    'journal_entry_id' => $journalEntry->id,
                    'date' => $request->journal_date,
                    'account_id' => $accountEntry['account_id'],
                    'reference' => $accountEntry['reference'] ?? null,
                    'description' => $accountEntry['description'] ?? null,
                    'debit_amount' => $debit_amount,
                    'credit_amount' => $credit_amount,
                    'amount' => $amountValue,
                    'date' => $transactionDate,
                    'transaction_date' => $transactionDate,
                    'edited_by' => $currentUser->id,
                ]);

                if ($debit_amount) {
                    $totalDebitAmount += $debit_amount;
                } else {
                    $totalCreditAmount += $credit_amount;
                }
            }

            // Check if the credit amount is equal to the debit amount
            if ($totalCreditAmount !== $totalDebitAmount) {
                throw new BadRequestException("Debit amount is not equal to Credit amount.", Response::HTTP_CONFLICT);
            }

            DB::commit();
            return $journalEntry->refresh();
        } catch (\Throwable $th) {
            DB::rollback(); // Rollback changes if any error occurs
            throw $th; // Re-throw the exception to be caught by the controller
        }
    }

    public function delete($id)
    {
        try {
            DB::beginTransaction();

            $record = FinanceJournalEntry::where('id', $id)->first();
            if (is_null($record)) {
                throw new BadRequestException("Record not found!", Response::HTTP_NOT_FOUND);
            }

            if ($record->is_default) {
                throw new BadRequestException("You cannot delete a default account.", Response::HTTP_CONFLICT);
            }

            // Delete related account entries
            $record->accountEntries()->delete();

            // Now delete the journal entry itself
            $record->delete();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
