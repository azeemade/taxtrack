<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountProvisioner
{
    /**
     * Ensure a chart account exists for $slug (per company).
     * If missing, create it under the given subcategory slug.
     *
     * @return int chart_of_accounts.id
     */
    public static function ensure(
        int $companyId,
        string $accountSlug,
        string $accountName,
        string $subCategorySlug
    ): int {
        // already exists?
        $existing = DB::table('finance_chart_of_accounts')
            ->where('slug', $accountSlug)
            ->where('company_id', $companyId)
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        // locate subcategory
        $sub = DB::table('finance_account_sub_categories')
            ->where('slug', $subCategorySlug)
            ->first(['id', 'account_type_id', 'account_category_id', 'ref_code']);

        if (!$sub) {
            throw new \RuntimeException("Subcategory '{$subCategorySlug}' not found.");
        }

        // generate account number based on subcategory ref_code + sequence (NNNN)
        $prefix = preg_replace('/\D+/', '', (string) $sub->ref_code) ?: '999999';

        $lastAccount = DB::table('finance_chart_of_accounts')
            ->where('company_id', $companyId)
            ->where('account_sub_category_id', $sub->id)
            ->where('account_number', 'like', $prefix . '%')
            ->orderBy('account_number', 'desc')
            ->value('account_number');

        $lastSeq = $lastAccount
            ? (int) substr($lastAccount, strlen($prefix))
            : 0;


        $accountNumber = $prefix . str_pad((string) ($lastSeq + 1), 4, '0', STR_PAD_LEFT);

        // create
        return (int) DB::table('finance_chart_of_accounts')->insertGetId([
            'account_type_id'        => $sub->account_type_id,
            'account_category_id'    => $sub->account_category_id,
            'account_sub_category_id' => $sub->id,
            'edited_by'              => null,
            'name'                   => $accountName,
            'currency'               => null,
            'account_number'         => $accountNumber,
            'old_account_number'     => '',
            'second_leg_account_id'  => null,
            'reference_code'         => $accountName,
            'description'            => $accountName,
            'slug'                   => Str::slug($accountSlug),
            'opening_balance'        => '0.00',
            'balance_date'           => null,
            'balance'                => null,
            "status"                 => "published",
            'is_active'              => "true",
            'is_default'             => "true",
            'is_hidden'              => "false",
            'created_at'             => now(),
            'updated_at'             => now(),
            'deleted_at'             => null,
            'company_id'             => $companyId,
        ]);
    }
}
