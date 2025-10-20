<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;

class AccountOpeningPosting
{
    /**
     * Keep this as your initial simple bank opening posting.
     * Expects $bank to have: company_id, id, opening_balance, balance_date, name.
     * Uses Opening Balance Equity from config('accounts.OPENING_BALANCE_EQUITY').
     */
    public function bankOpeningBalance(object $bank, ?int $editedBy = null): ?int
    {
        $amount = (float)($bank->opening_balance ?? 0);
        $date   = $bank->balance_date ?? null;

        if ($amount == 0 || !$date) {
            return null; // nothing to post
        }

        $companyId       = (int)$bank->company_id;
        $offsetAccountId = $this->openingOffsetAccountId($companyId);

        return DB::transaction(function () use ($companyId, $bank, $amount, $date, $editedBy, $offsetAccountId) {
            // Header
            $jeId = DB::table('finance_journal_entries')->insertGetId([
                'company_id' => $companyId,
                'date'       => $date,
                'type'       => 'opening_balance',
                'reference'  => "Opening balance for {$bank->name}",
                'description'=> "Opening balance entry for {$bank->name}",
                'status'     => 'published',
                'edited_by'  => $editedBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Line 1: Dr Bank (using the same account_id style you used before)
            DB::table('finance_account_entries')->insert([
                'journal_entry_id' => $jeId,
                'account_id'       => $bank->id,
                'debit_amount'     => $amount,
                'credit_amount'    => 0,
                'reference'        => "opening:bank:{$bank->id}",
                'description'      => 'Opening balance',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Line 2: Cr Opening Balance Equity
            DB::table('finance_account_entries')->insert([
                'journal_entry_id' => $jeId,
                'account_id'       => $offsetAccountId,
                'debit_amount'     => 0,
                'credit_amount'    => $amount,
                'reference'        => "opening:bank:{$bank->id}",
                'description'      => 'Opening balance offset',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            return $jeId;
        });
    }

    /**
     * Generic opening for ANY account.
     * Pass the $account object — it MUST have: id, company_id, account_sub_category_id (or sub_category_id),
     * and you pass the amount + balance date.
     *
     * Dr/Cr rules (by TYPE derived from subcategory):
     *  - Asset(1), Expense(5)  => normal side = DR
     *  - Liability(2), Equity(3), Income(4) => normal side = CR
     * If amount is negative, we flip the side.
     */
    public function openForAccount(
        object $account,
        float $amount,
        string $balanceDate,
        ?int $editedBy = null,
        ?string $reference = null
    ): ?int {
        if ($amount == 0 || !$balanceDate) {
            return null;
        }

        $companyId     = (int)$account->company_id;
        $accountId     = (int)$account->id;
        $subCategoryId = (int)($account->account_sub_category_id ?? $account->sub_category_id);
        $typeId        = $this->typeIdFromSubCategory($subCategoryId);
        $offsetId      = $this->openingOffsetAccountId($companyId);

        $normal = $this->normalSide($typeId); // 'debit' | 'credit'
        $post   = ($amount >= 0) ? $normal : ($normal === 'debit' ? 'credit' : 'debit');
        $amt    = abs($amount);

        $ref = $reference ?: "opening:acct:{$accountId}:{$balanceDate}";

        return DB::transaction(function () use ($companyId, $balanceDate, $editedBy, $ref, $accountId, $offsetId, $post, $amt) {
            $jeId = DB::table('finance_journal_entries')->insertGetId([
                'company_id' => $companyId,
                'date'       => $balanceDate,
                'type'       => 'opening_balance',
                'reference'  => $ref,
                'description'=> 'Opening balance entry',
                'status'     => 'published',
                'edited_by'  => $editedBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Target line
            DB::table('finance_account_entries')->insert([
                'journal_entry_id' => $jeId,
                'account_id'       => $accountId,
                'debit_amount'     => $post === 'debit' ? $amt : 0,
                'credit_amount'    => $post === 'credit' ? $amt : 0,
                'reference'        => $ref,
                'description'      => 'Opening balance',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Offset line (inverse)
            DB::table('finance_account_entries')->insert([
                'journal_entry_id' => $jeId,
                'account_id'       => $offsetId,
                'debit_amount'     => $post === 'credit' ? $amt : 0,
                'credit_amount'    => $post === 'debit' ? $amt : 0,
                'reference'        => $ref,
                'description'      => 'Opening balance offset',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            return $jeId;
        });
    }

    // ----------- tiny helpers (kept minimal) -----------

    protected function openingOffsetAccountId(int $companyId): int
    {
        // If your config stores a slug, resolve with your helper. Keeping it simple here:
        $conf = config('accounts.RETAINED_EARNINGS') ?? config('accounts.OPENING_BALANCE_EQUITY');
        if (is_numeric($conf)) return (int)$conf;

        if (class_exists(\App\Helpers\Posting\AccountHelper::class) && is_string($conf)) {
            return \App\Helpers\Posting\AccountHelper::idBySlug($companyId, $conf);
        }

        throw new \RuntimeException('Configure accounts.RETAINED_EARNINGS (ID or slug).');
    }

    protected function typeIdFromSubCategory(int $subCategoryId): int
    {
        return (int) DB::table('finance_account_sub_categories as sub')
            ->join('finance_account_categories as cat', 'cat.id', '=', 'sub.account_category_id')
            ->join('finance_account_types as t', 't.id', '=', 'cat.account_type_id')
            ->where('sub.id', $subCategoryId)
            ->value('t.id');
    }

    protected function normalSide(int $typeId): string
    {
        // 1 Asset, 5 Expense => DR; 2 Liability, 3 Equity, 4 Income => CR
        return in_array($typeId, [1, 5], true) ? 'debit' : 'credit';
    }
}
