<?php

namespace App\Helpers\Posting;

use App\Models\FinanceChartOfAccount;
use Illuminate\Support\Facades\DB;

class CoaProvisionerFromConfig
{
    /**
     * Provision COA for a company from code templates (config/coa_templates.php).
     * Returns counts for visibility.
     */
    public function provisionForCompany(int $companyId, ?int $editedBy = null): array
    {
        DB::beginTransaction();

        try {
            $subcats  = config('coa_templates.sub_categories', []);
            $accounts = config('coa_templates.chart_of_accounts', []);

            // 1) Ensure sub-categories exist (global lookup; idempotent by slug)
            $createdSub = 0; $updatedSub = 0;

            foreach ($subcats as $sc) {
                $existing = DB::table('finance_account_sub_categories')
                    ->where('slug', $sc['slug'])
                    ->first();

                $payload = [
                    'account_type_id'     => $sc['account_type_id'],
                    'account_category_id' => $sc['account_category_id'],
                    'name'                => $sc['name'],
                    'ref_code'            => $sc['ref_code'] ?? null,
                    'description'         => $sc['description'] ?? null,
                    'is_default'          => true,   // metadata only; not read from DB
                    'updated_at'          => now(),
                ];

                if ($existing) {
                    DB::table('finance_account_sub_categories')
                        ->where('id', $existing->id)
                        ->update($payload);
                    $updatedSub++;
                } else {
                    $payload['slug']       = $sc['slug'];
                    $payload['created_at'] = now();
                    DB::table('finance_account_sub_categories')->insert($payload);
                    $createdSub++;
                }
            }

            // cache sub-category IDs by slug (for account inserts)
            $subcatIdBySlug = DB::table('finance_account_sub_categories')
                ->pluck('id', 'slug')
                ->all();

            // 2) Upsert company COA by (company_id, slug)
            $createdAcc = 0; $updatedAcc = 0;

            foreach ($accounts as $acc) {
                $slug         = $acc['slug'];
                $subcatSlug   = $acc['account_sub_category_slug'] ?? null;
                $subcatId     = $subcatSlug
                    ? ($subcatIdBySlug[$subcatSlug] ?? null)
                    : ($acc['account_sub_category_id'] ?? null);

                if (!$subcatId) {
                    throw new \RuntimeException("Missing account_sub_category for account slug [$slug]");
                }

                $existing = DB::table('finance_chart_of_accounts')
                    ->where('company_id', $companyId)
                    ->where('slug', $slug)
                    ->first();

                $payload = [
                    'account_type_id'         => $acc['account_type_id'],
                    'account_category_id'     => $acc['account_category_id'],
                    'account_sub_category_id' => $subcatId,
                    'edited_by'               => $editedBy,
                    'name'                    => $acc['name'],
                    'currency'                => $acc['currency'] ?? null,
                    'reference_code'          => $acc['reference_code'] ?? $acc['name'],
                    'description'             => $acc['description'] ?? $acc['name'],
                    'is_active'               => $acc['is_active'] ?? 'true',
                    'is_default'              =>$acc['is_default'] ?? 'true',
                    'is_hidden'               => $acc['is_hidden'] ?? 'false',
                    'status'                  => $acc['status'] ?? 'published',
                    'updated_at'              => now(),
                    'deleted_at'              => null,
                    'company_id'              => $companyId,
                ];

                if ($existing) {
                    // Preserve existing account_number if present (avoid surprises)
                    $payload['account_number'] = $existing->account_number ?: ($acc['account_number'] ?? null);

                    DB::table('finance_chart_of_accounts')
                        ->where('id', $existing->id)
                        ->update($payload);

                    $updatedAcc++;
                } else {
                    $payload['slug']            = $slug;
                    $payload['account_number']  = $acc['account_number'] ?? null;
                    $payload['opening_balance'] = '0.00';
                    $payload['balance_date']    = null;
                    $payload['balance']         = null;
                    $payload['created_at']      = now();

                    DB::table('finance_chart_of_accounts')->insert($payload);
                    $createdAcc++;
                }
            }

            DB::commit();

            return compact('createdSub', 'updatedSub', 'createdAcc', 'updatedAcc');

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}