<?php

namespace App\Services\ChartOfAccount;

use App\Exceptions\BadRequestException;
use App\Exports\Accounting\ChartOfAccount\ChartOfAccountExport;
use App\Helpers\FinanceAccountBalanceHelper;
use App\Helpers\Posting\AccountOpeningPosting;
use App\Helpers\Posting\CoaProvisionerFromConfig;
use App\Models\FinanceAccountCategory;
use App\Models\FinanceAccountSubCategory;
use App\Models\FinanceAccountType;
use App\Models\FinanceChartOfAccount;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Imports\Accounting\ChartOfAccount\ChartOfAccountImport;
use App\Models\Bank;
use App\Models\Company;
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

            $userCompanyId = auth()->user()->current_company_id;

            $record = FinanceChartOfAccount::with([
                'accountType:id,name,slug',
                'accountCategory:id,name',
                'subCategory:id,name',
                'accountEntries' => function ($query) use ($request, $dateSearchParams) {
                    if ($dateSearchParams) {
                        $query->whereBetween('date', [$request->start_date, $request->end_date]);
                    }
                }
            ])
                
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
                ->when($dateSearchParams, function ($query) use ($request) {
                    $startDate = Carbon::parse($request->start_date);
                    $endDate = Carbon::parse($request->end_date);
                    return $query->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);
                })
                ->when($carbonDateFilter, function ($query) use ($carbonDateFilter) {
                    return $query->where('created_at', '>=', $carbonDateFilter);
                })
                ->where('company_id', $userCompanyId)
                ->orderBy("account_type_id", "ASC")
                ->orderBy('account_number', "ASC");

            $record = $export ? $record->get() : $record->paginate($limit);

            // Add balance information to each account
            $record->transform(function ($account) use ($request, $dateSearchParams) {
                $balanceInfo = FinanceAccountBalanceHelper::calculateCurrentBalance($account, $request->start_date, $request->end_date, $dateSearchParams);

                $account->current_balance = $balanceInfo['current_balance'];
                $account->balance_type = $balanceInfo['balance_type'];
                $account->total_debit = $balanceInfo['total_debit'];
                $account->total_credit = $balanceInfo['total_credit'];

                return $account;
            });

            if ($export) {
                $fileName = 'chart_of_accounts_' . now()->format('Ymd_His') . '.xlsx';

                return Excel::download(
                    new ChartOfAccountExport($record, $request->start_date, $request->end_date),
                    $fileName
                );
            }

            return $record;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function allChartOfAccountNotPaginated()
    {
        try {
            return FinanceChartOfAccount::where('company_id',  auth()->user()->current_company_id)
                ->select("id", "name", 'account_number')->orderBy("name", "ASC")
                ->get();
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function allSubCategoriesNotPaginated()
    {
        try {
            return FinanceAccountSubCategory::select("id", "name")->orderBy("name", "ASC")
                ->get();
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

            if (!is_null($request->account_number)) {
                $existingAccount = FinanceChartOfAccount::where('account_number', $request->account_number)->exists();
                if ($existingAccount) {
                    throw new BadRequestException("Account number already exists for another account.", Response::HTTP_CONFLICT);
                }
                $accountNumber = $request->account_number;
            } else {
                // Generate Account Number
                $latestAccount = FinanceChartOfAccount::where('account_sub_category_id', $getSubCategoryInfo->id)->latest()->first();
                $latestId = $latestAccount ? intval(substr($latestAccount->account_number, -4)) : 0;

                do {
                    $latestId++;
                    $newId = $getSubCategoryInfo->ref_code . str_pad($latestId, 4, '0', STR_PAD_LEFT);
                    $existingAccount = FinanceChartOfAccount::where('account_number', $newId)->exists();
                } while ($existingAccount);

                $accountNumber = $newId;
            }

            // Create Account
            $coa = FinanceChartOfAccount::create([
                'account_type_id' => $getSubCategoryInfo->account_type_id,
                'account_category_id' => $getSubCategoryInfo->account_category_id,
                'account_sub_category_id' => $request->account_sub_category_id,
                'company_id' => auth()->user()->current_company_id,
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'account_number' => $accountNumber,
                'description' => $request->description ?? $request->name,
                'holder_name' => $request->holder_name,
                'account_type' => $request->account_type,
                'currency' => $request->currency, //eg Euro
                'reference_code' => $request->name,
                'opening_balance' => $request->opening_balance,
                'balance_date' => $request->balance_date,
                'status' => $request->status ?? "published",
                'is_active' => $request->status == "published" ? "true" : "false",
                'edited_by' => $user->id
            ]);

            if (!$coa) {
                throw new BadRequestException("Unable to create account.", Response::HTTP_CONFLICT);
            }

            DB::commit();
            return $coa;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }


    public function createCashAndBankAccount($request)
    {
        DB::beginTransaction();
        try {
            $user = auth()->user();
            $getSubCategoryInfo = FinanceAccountSubCategory::where('name', 'Cash and Bank')->first();
            if (!$getSubCategoryInfo) {
                throw new BadRequestException("Account sub-category not found!", Response::HTTP_NOT_FOUND);
            }

            $existingAccount = FinanceChartOfAccount::where("account_sub_category_id", $getSubCategoryInfo->id)
                ->where("name", $request->name)
                ->exists();

            if ($existingAccount) {
                throw new BadRequestException("Name already exists for the account type.", Response::HTTP_CONFLICT);
            }

            if (!is_null($request->account_number)) {
                $existingAccount = FinanceChartOfAccount::where('account_number', $request->account_number)->exists();
                if ($existingAccount) {
                    throw new BadRequestException("Account number already exists for another account.", Response::HTTP_CONFLICT);
                }
                $accountNumber = $request->account_number;
            } else {
                // Generate Account Number
                $latestAccount = FinanceChartOfAccount::where('account_sub_category_id', $getSubCategoryInfo->id)->latest()->first();
                $latestId = $latestAccount ? intval(substr($latestAccount->account_number, -4)) : 0;

                do {
                    $latestId++;
                    $newId = $getSubCategoryInfo->ref_code . str_pad($latestId, 4, '0', STR_PAD_LEFT);
                    $existingAccount = FinanceChartOfAccount::where('account_number', $newId)->exists();
                } while ($existingAccount);

                $accountNumber = $newId;
            }

            $bank = Bank::where('id', $request->bank_id)->first();
            if (!$bank) {
                throw new BadRequestException("Bank does not exist.", Response::HTTP_CONFLICT);
            }



            // Create Account
            $coa = FinanceChartOfAccount::create([
                'account_type_id' => $getSubCategoryInfo->account_type_id,
                'account_category_id' => $getSubCategoryInfo->account_category_id,
                'account_sub_category_id' => $getSubCategoryInfo->id,
                'company_id' => auth()->user()->current_company_id,
                'name' => $bank->name,
                'slug' => Str::slug($bank->name),
                'account_number' => $accountNumber,
                'description' => $request->description ?? $bank->name,
                'holder_name' => $request->holder_name,
                'bank_id' => $request->bank_id,
                'account_type' => $request->account_type,
                'currency' => $request->currency, //eg Euro
                'reference_code' => $bank->name,
                'opening_balance' => $request->opening_balance,
                'balance_date' => $request->balance_date,
                'status' => $request->status ?? "published",
                'is_active' => $request->status == "published" ? "true" : "false",
                'edited_by' => $user->id
            ]);

            if (!$coa) {
                throw new BadRequestException("Unable to create account.", Response::HTTP_CONFLICT);
            }

            if ($bank->opening_balance > 0 && $bank->balance_date) {
                (new AccountOpeningPosting)->bankOpeningBalance($bank);
            }

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
            $record = FinanceChartOfAccount::where('company_id', auth()->user()->current_company_id)
                ->with("accountType:id,name", "subCategory:id,name")
                ->where('id', $id)
                ->first();

            if (is_null($record)) {
                throw new BadRequestException("Record not found!", Response::HTTP_NOT_FOUND);
            }

            $response = $record->toArray();

            if (!is_null($record->bank_id)) {
                $bank = Bank::where('id', $record->bank_id)->first();
                if (!$bank) {
                    throw new BadRequestException("Bank does not exist.", Response::HTTP_CONFLICT);
                }
                $response['bank'] = $bank;
            }

            return $response;
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
                // ->where("name", $request->name)
                ->where('id', '!=', $id)
                ->exists();

            if ($nameExists) {
                throw new BadRequestException("Name already exists for the account type.", Response::HTTP_CONFLICT);
            }

            $bank = Bank::where('id', $request->bank_id)->first();
            if (!$bank) {
                throw new BadRequestException("Bank does not exist.", Response::HTTP_CONFLICT);
            }

            $record->update([
                'account_type_id' =>  $getSubCategoryInfo->account_type_id,
                'account_category_id' =>  $getSubCategoryInfo->account_category_id,
                'account_sub_category_id' =>  $getSubCategoryInfo->id,
                'company_id' => auth()->user()->current_company_id,
                'name' => $bank->name ?? $record->name,
                'slug' => Str::slug($bank->name) ?? Str::slug($record->name),
                'description' => $request->description ?? $record->description,
                'reference_code' => $request->reference_code ?? $record->reference_code,
                'opening_balance' => $request->opening_balance ?? $record->opening_balance,
                'balance_date' => $request->balance_date ?? $record->balance_date,
                'is_active' =>  $request->filled('status')
                    ? ($request->status == "published" ? "true" : "false")
                    : $record->is_active,
                'status' => $request->status ?? $record->status,
                'edited_by' => $currentUser->id
            ]);

            DB::commit();

            return $record; // Return updated record

        } catch (\Throwable $th) {
            DB::rollback(); // Rollback changes if any error occurs
            throw $th; // Re-throw the exception to be caught by the controller
        }
    }


    public function updateCashAndBankAccount($request, $id)
    {
        DB::beginTransaction();

        try {
            $currentUser = auth()->user();

            $record = FinanceChartOfAccount::find($id);
            if (!$record) {
                throw new BadRequestException("Record not found!", Response::HTTP_NOT_FOUND);
            }

            // Check if name already exists for the given sub-category
            $nameExists = FinanceChartOfAccount::where("account_sub_category_id", $record->account_sub_category_id)
                ->where("name", $request->name)
                ->where('id', '!=', $id)
                ->exists();

            if ($nameExists) {
                throw new BadRequestException("Name already exists for the account type.", Response::HTTP_CONFLICT);
            }

            $accountNumber = $record->account_number;
            if (!is_null($request->account_number)) {
                $existingAccount = FinanceChartOfAccount::where('account_number', $request->account_number)->where('id', '!=', $id)->exists();
                if ($existingAccount) {
                    throw new BadRequestException("Account number already exists for another account.", Response::HTTP_CONFLICT);
                }
                $accountNumber = $request->account_number;
            }

            $record->update([
                'name' => $request->name ?? $record->name,
                'slug' => Str::slug($request->name) ?? Str::slug($record->name),
                'description' => $request->description ?? $record->description,
                'reference_code' => $request->reference_code ?? $record->reference_code,
                'account_number' => $accountNumber,
                'holder_name' => $request->holder_name ?? $record->holder_name,
                'bank_id' => $request->bank_id ?? $record->bank_id,
                'account_type' => $request->account_type ?? $record->account_type,
                'currency' => $request->currency ?? $record->currency, //eg Euro
                'reference_code' => $request->reference_code ?? $record->reference_code,
                'opening_balance' => $request->opening_balance ?? $record->opening_balance,
                'balance_date' => $request->balance_date ?? $record->balance_date,
                'is_active' =>  $request->filled('status')
                    ? ($request->status == "published" ? "true" : "false")
                    : $record->is_active,
                'status' => $request->status ?? $record->status,
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

            $record = FinanceChartOfAccount::where('company_id',  auth()->user()->current_company_id)
                ->where('id', $id)
                ->first();

            if (is_null($record)) {
                throw new BadRequestException("Record not found!", Response::HTTP_NOT_FOUND);
            }

            if ($record->is_default == "true") {
                throw new BadRequestException("You cannot update a default account.", Response::HTTP_CONFLICT);
            }

            $newStatus = $record->status === 'published' ? 'unpublished' : 'published';
            $newActiveStatus = $record->is_active === 'true' ? 'false' : 'true';

            $record->update([
                'status' => $newStatus,
                'is_active' => $newActiveStatus,
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

    public function getAccountsByType(string $accountType)
    {
        try {
            $records = FinanceChartOfAccount::where('company_id',  auth()->user()->current_company_id)
                ->select('id', 'name', 'account_type_id', 'account_number')
                ->whereHas('accountType', function ($query) use ($accountType) {
                    $query->where('slug', $accountType);
                })->get();

            return $records;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function getAccountsBySubCategoryID($id)
    {
        try {
            $records = FinanceChartOfAccount::where('company_id',  auth()->user()->current_company_id)
                ->select('id', 'name', 'account_number', 'account_number')
                ->where("account_sub_category_id", $id)
                ->get();

            return $records;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function getAccountsBySubCategoryName(string $accountSubCategoryName)
    {
        try {
            $records = FinanceChartOfAccount::where('company_id',  auth()->user()->current_company_id)
                ->select('id', 'name', 'account_sub_category_id', 'account_number')
                ->whereHas('subCategory', function ($query) use ($accountSubCategoryName) {
                    $query->where('slug', $accountSubCategoryName);
                })->get();

            return $records;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function importAccount($request)
    {
        DB::beginTransaction();

        try {
            $import = new ChartOfAccountImport;
            Excel::import($import, $request->file('file'));

            if (!empty($import->getErrors())) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Validation errors in imported file',
                    'errors' => $import->getErrors(),
                    'code' => 422
                ];
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Accounts imported successfully',
                'data' => null
            ];
        } catch (\Throwable $error) {
            DB::rollBack();
            throw $error;
        }
    }

    public function allCashAndBankAccounts($request)
    {
        try {
            $paginate = $request->paginate ?? true;
            $limit = $request->limit ?? 10;
            $sortBy = $request->sort_by;
            $filterBy = $request->filter_by;
            $carbonDateFilter = $request->date_filter;
            $export = $request->export;
            $searchParams = $request->q;
            (!is_null($request->start_date) && !is_null($request->end_date)) ? $dateSearchParams = true : $dateSearchParams = false;

            $userCompanyId = auth()->user()->current_company_id;

            $record = FinanceChartOfAccount::with([
                'accountType:id,name,slug',
                'accountCategory:id,name',
                'subCategory:id,name,slug',
                'accountEntries' => function ($query) use ($request, $dateSearchParams) {
                    if ($dateSearchParams) {
                        $query->whereBetween('date', [$request->start_date, $request->end_date]);
                    }
                }
            ])
                ->whereHas('subCategory', function ($query) {
                    $query->where('slug', 'cash-and-bank');
                })
                ->where('company_id', $userCompanyId)
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
                ->when($dateSearchParams, function ($query) use ($request) {
                    $startDate = Carbon::parse($request->start_date);
                    $endDate = Carbon::parse($request->end_date);
                    return $query->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate]);
                })
                ->when($carbonDateFilter, function ($query) use ($carbonDateFilter) {
                    return $query->where('created_at', '>=', $carbonDateFilter);
                })
                ->orderBy("account_type_id", "ASC")
                ->orderBy('account_number', "ASC");

            // Handle export case first
            if ($export) {
                $records = $record->get();

                $fileName = 'chart_of_accounts_' . now()->format('Ymd_His') . '.xlsx';
                return Excel::download(
                    new ChartOfAccountExport($records, $request->start_date, $request->end_date),
                    $fileName
                );
            }

            // Then handle regular response
            $records = $paginate ? $record->paginate($limit) : $record->get();

            // Add balance information to each account
            $records->transform(function ($account) use ($request, $dateSearchParams) {
                $balanceInfo = FinanceAccountBalanceHelper::calculateCurrentBalance($account, $request->start_date, $request->end_date, $dateSearchParams);

                $account->current_balance = $balanceInfo['current_balance'];
                $account->balance_type = $balanceInfo['balance_type'];
                $account->total_debit = $balanceInfo['total_debit'];
                $account->total_credit = $balanceInfo['total_credit'];

                return $account;
            });

            return $records;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function indexCardView($request)
    {
        try {
            $limit = $request->limit ?? 10;
            $dateSearchParams = (!is_null($request->start_date) && !is_null($request->end_date));

            $userCompanyId = auth()->user()->current_company_id;

            $records = FinanceChartOfAccount::select("id", "name", "account_number")
                ->whereHas('subCategory', function ($query) {
                    $query->where('slug', 'cash-and-bank');
                })
                ->where('company_id', $userCompanyId)
                ->orderBy("account_type_id", "ASC")
                ->orderBy('account_number', "ASC")
                ->limit($limit)
                ->get();
            return $records;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function loadDefaultAccounts()
    {
        try {
            DB::beginTransaction();

            $company = Company::all();
            foreach ($company as $comp) {
                $user = $comp->staff()->first();
                $editedBy = $user ? $user->id : null;
                (new CoaProvisionerFromConfig())
                    ->provisionForCompany($comp->id, $editedBy);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
