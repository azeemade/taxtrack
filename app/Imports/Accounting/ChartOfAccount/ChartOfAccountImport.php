<?php

namespace App\Imports\Accounting\ChartOfAccount;

use App\Models\FinanceAccountSubCategory;
use App\Models\FinanceChartOfAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ChartOfAccountImport implements ToModel, WithHeadingRow
{
    private $errors = [];

    public function model(array $row)
    {
        try {
            $user = auth()->user();

            // Validate required fields
            if (empty($row['account_subcategory_name'])) {
                $this->errors[] = "Account sub category name is required";
                return null;
            }

            if (empty($row['name'])) {
                $this->errors[] = "Account name is required";
                return null;
            }

            $accountSubCategory = FinanceAccountSubCategory::where("name", $row['account_subcategory_name'])->first();
            if (!$accountSubCategory) {
                $this->errors[] = "Sub category with ref code '{$row['account_subcategory_name']}' not found";
                return null;
            }

            $existingAccount = FinanceChartOfAccount::where("account_sub_category_id", $accountSubCategory->id)
                ->where("name", $row['name'])
                ->exists();

            if ($existingAccount) {
                $this->errors[] = "Account '{$row['name']}' already exists for the account type.";
                return null;
            }

            // Generate account number
            // $latestAccount = FinanceChartOfAccount::where('account_sub_category_id', $accountSubCategory->id)
            //     ->latest()
            //     ->first();

            $latestId = 0;

            // if ($latestAccount) {
            //     $numberPart = str_replace($accountSubCategory->ref_code, '', $latestAccount->account_number);
            //     $latestId = is_numeric($numberPart) ? (int)$numberPart : 0;
            // }

            $accountNumber = $this->generateUniqueAccountNumber($accountSubCategory->id, $accountSubCategory->ref_code);


            // $accountNumber = $this->generateUniqueAccountNumber($accountSubCategory->ref_code, $latestId);

            // $latestId = $latestAccount ? intval(substr($latestAccount->account_number, -4)) : 0;
            // $accountNumber = $this->generateUniqueAccountNumber($accountSubCategory->ref_code, $latestId);

            return new FinanceChartOfAccount([
                "account_type_id" => $accountSubCategory->account_type_id,
                "account_category_id" => $accountSubCategory->account_category_id,
                "account_sub_category_id" => $accountSubCategory->id,
                "edited_by" => $user->id,
                "name" => $row['name'],
                "account_number" => $accountNumber,
                "slug" => Str::slug($row['name']),
                "reference_code" => $row['name'],
                "description" => $row['description'] ?? $row['name'],
                "opening_balance" => $row['opening_balance'] ?? 0.00,
                "balance_date" => $row['balance_date'] ?? null,
                "company_id" => $user->current_company_id,
            ]);
        } catch (\Throwable $th) {
            $this->errors[] = "Error processing row: " . $th->getMessage();
            return null;
        }
    }

    // protected function generateUniqueAccountNumber($refCode, $latestId)
    // {
    //     do {
    //         $latestId++;
    //         $newId = $refCode . str_pad($latestId, 4, '0', STR_PAD_LEFT);
    //         $existingAccount = FinanceChartOfAccount::where('account_number', $newId)->exists();
    //     } while ($existingAccount);

    //     return $newId;
    // }

    protected function generateUniqueAccountNumber($subCategoryID, $refCode)
    {
        $latestAccount = FinanceChartOfAccount::where('company_id', auth()->user()->current_company_id)->where('account_sub_category_id', $subCategoryID)
            ->orderByDesc('account_number')
            ->first();

        $latestId = $latestAccount ? intval(substr($latestAccount->account_number, -4)) : 0;

        Log::info("Latest account:", ['account' => $latestAccount]);
        Log::info("Latest ID: $latestId");

        do {
            $latestId++;
            $newId = $refCode . str_pad($latestId, 4, '0', STR_PAD_LEFT);
            
            // Check if the account number exists, including soft-deleted ones
            $existingAccount = FinanceChartOfAccount::where('company_id', auth()->user()->current_company_id)->where('account_number', $newId)
                ->first();  // Fetch the first record regardless of soft delete status
        
            if ($existingAccount && $existingAccount->trashed()) {
                // If it's soft deleted, allow the new account to reuse this number
                break;
            }
        } while ($existingAccount);  // Keep trying until no active record exists

        return $newId;
    }


    public function getErrors()
    {
        return $this->errors;
    }
}
