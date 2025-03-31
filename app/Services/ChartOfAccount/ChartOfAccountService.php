<?php

namespace App\Services\ChartOfAccount;

use App\Exceptions\BadRequestException;
use App\Exports\Accounting\ChartOfAccount\ChartOfAccountExport;
use App\Models\FinanceAccountCategory;
use App\Models\FinanceAccountSubCategory;
use App\Models\FinanceAccountType;
use App\Models\FinanceChartOfAccount;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ChartOfAccountService
{
    public function allChartOfAccount($request)
    {
        try {
            $limit = $request->limit ?? 10;
            $sortBy = $request->sort_by;
            $filterBy = $request->filter_by;
            $carbonDateFilter = $request->date_filter;
            $export = $request->export;
            $searchParams = $request->q;
            (!is_null($request->start_date) && !is_null($request->end_date)) ? $dateSearchParams = true : $dateSearchParams = false;


            $record = FinanceChartOfAccount::with('accountType:id,name', 'accountCategory:id,name', 'subCategory:id,name')
                // ->withCount([
                //     'accountEntries as debit_amount' => function ($query) use ($previousYear, $currentYear) {
                //         $query->select(DB::raw('SUM(debit_amount)'))
                //             ->whereYear('date', $previousYear)
                //             ->orWhereYear('date', $currentYear);
                //     },
                //     'accountEntries as credit_amount' => function ($query) use ($previousYear, $currentYear) {
                //         $query->select(DB::raw('SUM(credit_amount)'))
                //             ->whereYear('date', $previousYear)
                //             ->orWhereYear('date', $currentYear);
                //     }
                // ])
                // ->where(function ($query) use ($previousYear, $currentYear) {
                //     $query->whereHas('accountEntries', function ($subQuery) use ($previousYear, $currentYear) {
                //         $subQuery->whereYear('date', $previousYear)
                //             ->orWhereYear('date', $currentYear);
                //     })
                //     ->orWhereDoesntHave('accountEntries');
                // })
                ->when($searchParams, function ($query) use ($searchParams) {
                    return $query->where('name', 'LIKE', '%' . $searchParams . '%')
                        ->orWhere('account_number', $searchParams)
                        ->orWhereHas('accountCategory', function ($query) use ($searchParams) {
                            $query->where('name', 'LIKE', '%' . $searchParams . '%');
                        })
                        ->orWhereHas('subCategory', function ($query) use ($searchParams) {
                            $query->where('name', 'LIKE', '%' . $searchParams . '%');
                        });
                })
                ->when($filterBy, function ($query) use ($filterBy) {
                    return $query->where('is_active', $filterBy);
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
                ->when($dateSearchParams, function ($query) use ($request) {
                    $startDate = Carbon::parse($request->start_date);
                    $endDate = Carbon::parse($request->end_date);
                    return $query->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);
                })
                ->when($carbonDateFilter, function ($query) use ($carbonDateFilter) {
                    return $query->where('created_at', '>=', $carbonDateFilter);
                })
                ->orderBy("account_type_id", "ASC")
                ->orderBy("account_number", "ASC");

            $record = $export ? $record->get() : $record->paginate($limit);

            if ($export) {
                return Excel::download(new ChartOfAccountExport($record), 'chartofaccountreportdata.xlsx');
            }

            return $record;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function allChartOfAccountNotPaginated()
    {
        try {
            return FinanceChartOfAccount::select("id", "name")->orderBy("name", "ASC")->get();
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function allSubCategoriesNotPaginated()
    {
        try {
            return FinanceAccountSubCategory::select("id", "name")->orderBy("name", "ASC")->get();
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function createAccount($request)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $getSubCategoryInfo = FinanceAccountSubCategory::find($request->account_sub_category_id);

            if (!$getSubCategoryInfo) {
                throw new BadRequestException("Account sub-category not found!", Response::HTTP_NOT_FOUND);
            }

            $existingAccount = FinanceChartOfAccount::where("account_sub_category_id", $getSubCategoryInfo->id)
                ->where("name", $request->name)
                ->exists();

            if ($existingAccount) {
                throw new BadRequestException("Name already exists for the account type.", Response::HTTP_CONFLICT);
            }

            // Generate Account Number
            $latestAccount = FinanceChartOfAccount::where('account_sub_category_id', $getSubCategoryInfo->id)->latest()->first();
            $latestId = $latestAccount ? intval(substr($latestAccount->account_number, -4)) : 0;

            do {
                $latestId++;
                $newId = $getSubCategoryInfo->ref_code . str_pad($latestId, 4, '0', STR_PAD_LEFT);
                $existingAccount = FinanceChartOfAccount::where('account_number', $newId)->exists();
            } while ($existingAccount);

            $accountNumber = $newId;

            // Create Account
            $coa = FinanceChartOfAccount::create([
                'account_type_id' => $getSubCategoryInfo->account_type_id,
                'account_category_id' => $getSubCategoryInfo->account_category_id,
                'account_sub_category_id' => $request->account_sub_category_id,
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'account_number' => $accountNumber,
                'description' => $request->description,
                'reference_code' => $request->reference_code ?? $request->name,
                'opening_balance' => $request->opening_balance,
                'balance_date' => $request->balance_date,
                'edited_by' => $user->id
            ]);

            DB::commit();
            return $coa;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function show($id)
    {
        try {
            $record = FinanceChartOfAccount::with("accountType:id,name", "subCategory:id,name")->where('id', $id)->first();
            if (is_null($record)) {
                throw new BadRequestException("Record not found!", Response::HTTP_NOT_FOUND);
            }

            return $record;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function updateAccount($request, $id)
    {
        DB::beginTransaction();

        try {
            $currentUser = auth()->user();

            $record = FinanceChartOfAccount::find($id);
            if (!$record) {
                throw new BadRequestException("Record not found!", Response::HTTP_NOT_FOUND);
            }

            $getSubCategoryInfo = FinanceAccountSubCategory::find($request->account_sub_category_id);
            if (!$getSubCategoryInfo) {
                throw new BadRequestException("Record not found!", Response::HTTP_NOT_FOUND);
            }

            // Check if name already exists for the given sub-category
            $nameExists = FinanceChartOfAccount::where("account_sub_category_id", $getSubCategoryInfo->id)
                ->where("name", $request->name)
                ->where('id', '!=', $id)
                ->exists(); // More efficient than first()

            if ($nameExists) {
                throw new BadRequestException("Name already exists for the account type.", Response::HTTP_CONFLICT);
            }

            $record->update([
                'account_type_id' =>  $getSubCategoryInfo->account_type_id,
                'account_category_id' =>  $getSubCategoryInfo->account_category_id,
                'account_sub_category_id' =>  $request->account_sub_category_id,
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'reference_code' => $request->reference_code ?? $request->name,
                'opening_balance' => $request->opening_balance,
                'edited_by' => $currentUser->id
            ]);

            DB::commit();

            return $record; // Return updated record

        } catch (\Throwable $th) {
            DB::rollback(); // Rollback changes if any error occurs
            throw $th; // Re-throw the exception to be caught by the controller
        }
    }

    public function delete($id)
    {
        try {
            DB::beginTransaction();

            $record = FinanceChartOfAccount::where('id', $id)->first();
            if (is_null($record)) {
                throw new BadRequestException("Record not found!", Response::HTTP_NOT_FOUND);
            }

            if ($record->is_default == "true") {
                throw new BadRequestException("You cannot delete a default account.", Response::HTTP_CONFLICT);
            }

            $record->delete();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function toggleStatus($id)
    {
        try {
            DB::beginTransaction();

            $record = FinanceChartOfAccount::where('id', $id)->first();
            if (is_null($record)) {
                throw new BadRequestException("Record not found!", Response::HTTP_NOT_FOUND);
            }

            if ($record->is_default == "true") {
                throw new BadRequestException("You cannot update a default account.", Response::HTTP_CONFLICT);
            }

            // Toggle between 'published' and 'unpublished'
            $newStatus = $record->status === 'published' ? 'unpublished' : 'published';

            $record->update([
                'status' => $newStatus,
            ]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    public function processBulkUpload($rows)
    {
        DB::beginTransaction();

        try {
            $errors = [];
            foreach ($rows as $index => $row) {
                $accountType = FinanceAccountType::where('name', $row['account_type'])->first();
                $accountCategory = FinanceAccountCategory::where('name', $row['account_category'])->first();
                $subCategory = FinanceAccountSubCategory::where('name', $row['sub_category'])->first();

                if (!$accountType || !$accountCategory || !$subCategory) {
                    $errors[] = "Row " . ($index + 1) . " has invalid type, category, or sub-category.";
                    continue;
                }

                FinanceChartOfAccount::create([
                    'account_type_id' => $accountType->id,
                    'account_category_id' => $accountCategory->id,
                    'account_sub_category_id' => $subCategory->id,
                    'name' => $row['account_name'],
                    'slug' => Str::slug($row['account_name']),
                    'description' => $row['description'],
                    'reference_code' => $row['reference_code'] ?? $row['account_name'],
                    'opening_balance' => $row['opening_balance'] ?? 0,
                ]);
            }

            DB::commit();

            if (!empty($errors)) {
                return ['message' => 'Upload completed with errors', 'errors' => $errors];
            }

            return ['message' => 'Accounts uploaded successfully'];
        } catch (\Throwable $th) {
            DB::rollBack();
            return ['error' => 'Bulk upload failed', 'message' => $th->getMessage()];
        }
    }
}
