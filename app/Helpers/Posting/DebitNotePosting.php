<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;

class DebitNotePosting
{
    /**
     * Idempotent: create/refresh the journal for a Debit Note with items across
     * purchase invoices and/or vendor bills.
     *
     * Posting:
     *   Dr Accounts Payable ..................... total gross debited to vendor
     *   Cr Line account(s) (expense/inventory) .. net reversal per line
     *   Cr VAT Input ............................ VAT reversal (if any)
     *
     * @return int journal_entry_id
     */
    public function syncForDebitNote(object $debitNote, int $companyId, ?int $editedBy = null): int
    {
        // Expected table/columns. Adjust names if yours differ.
        // items: debit_note_items(debit_note_id, model, model_id, line_item_id, debit_amount, debit_in_full)
        $items = DB::table('debit_note_items')
            ->where('debit_note_id', $debitNote->id)
            ->get(['model','model_id','line_item_id','debit_amount','debit_in_full']);

        if ($items->isEmpty()) {
            throw new \RuntimeException('No debit note items found.');
        }

        // Resolve control accounts
        $accAP       = $this->accountIdFromKeyOrSlug('AP', 'trade-accounts-payable', $companyId);
        $accVATInput = $this->accountIdFromKeyOrSlug('VAT_INPUT', 'input-vat', $companyId);
        $accCOGS     = $this->accountIdFromKeyOrSlug('COGS', 'cost-of-goods-sold', $companyId);

        // Aggregate: credits by line account (net), and VAT input credit
        $creditsByAccount = [];   // [account_id => amount]  (net)
        $sumVAT = 0.0;            // VAT reversal
        $sumGross = 0.0;          // total → Dr AP

        // Also try to detect a single parent document to link as parent_journal_entry_id
        $parents = [];
        foreach ($items as $it) {
            $parents[sprintf('%s#%d', $it->model, $it->model_id)] = [
                'model' => $it->model,
                'id'    => (int)$it->model_id,
            ];
        }

        foreach ($items as $it) {
            $gross = (float)($it->debit_amount ?? 0.0);
            if ($gross <= 0) continue;

            // Normalize model → documentable_type used on line_items table
            $model = $this->normalizeModel((string)$it->model);
            $split = $this->splitNetVatFromLineItem($model, (int)$it->model_id, (int)$it->line_item_id, $gross);

            $net  = max($split['net'], 0.0);
            $vat  = max($split['vat'], 0.0);

            // Determine original line's debit account to reverse (we CREDIT it here)
            $accId = $this->resolveLineAccountForReversal($model, (int)$it->model_id, (int)$it->line_item_id, $companyId, $accCOGS);

            // accumulate
            $creditsByAccount[$accId] = ($creditsByAccount[$accId] ?? 0) + $net;
            $sumVAT  += $vat;
            $sumGross += $gross; // hits AP (debit)
        }

        // Decide parent journal: only if all items point to the same doc
        $parentJe = null;
        if (count($parents) === 1) {
            $only = array_values($parents)[0];
            $parentJe = $this->fetchParentJournalEntryId($only['model'], $only['id']);
        }

        // ---- Upsert header + refresh lines (idempotent) ----
        return DB::transaction(function () use (
            $debitNote, $companyId, $editedBy, $parentJe, $creditsByAccount, $sumVAT, $sumGross, $accAP, $accVATInput
        ) {
            // 1) Header
            if (!empty($debitNote->journal_entry_id)) {
                $jeId = (int)$debitNote->journal_entry_id;

                DB::table('finance_journal_entries')->where('id', $jeId)->update([
                    'date'       => $debitNote->date_issued ?? now(),
                    'info'       => 'Debit Note '.$this->safeStr($debitNote->debit_note_number).' to vendor '.$this->safeStr($debitNote->vendor_id),
                    'edited_by'  => $editedBy,
                    'status'     => 'posted',
                    'updated_at' => now(),
                    'type'       => 'purchase_debit_note',
                ]);

                DB::table('finance_account_entries')->where('journal_entry_id', $jeId)->delete();
            } else {
                $jeId = DB::table('finance_journal_entries')->insertGetId([
                    'date'       => $debitNote->date_issued ?? now(),
                    'info'       => 'Debit Note '.$this->safeStr($debitNote->debit_note_number).' to vendor '.$this->safeStr($debitNote->vendor_id),
                    'edited_by'  => $editedBy,
                    'status'     => 'posted',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'company_id' => (int)$companyId,
                    'parent_journal_entry_id' => $parentJe,
                    'type'       => 'purchase_debit_note',
                ]);

                DB::table('debit_notes')->where('id', $debitNote->id)->update(['journal_entry_id' => $jeId]);
                if (property_exists($debitNote, 'journal_entry_id')) $debitNote->journal_entry_id = $jeId;
            }

            // 2) Lines
            $date = $debitNote->date_issued ?? now();
            $ref  = (string)($debitNote->debit_note_number ?? $debitNote->id);
            $lines = [];

            // Credit per-line original accounts (reverse expense/inventory)
            foreach ($creditsByAccount as $accId => $amt) {
                if ($amt > 0) {
                    $lines[] = $this->line($jeId, $date, (int)$accId, $ref, 'Reverse purchases (net)', 0, $amt);
                }
            }

            // Credit VAT Input (reverse recoverable VAT)
            if ($sumVAT > 0) {
                $lines[] = $this->line($jeId, $date, $accVATInput, $ref, 'Reverse VAT Input', 0, $sumVAT);
            }

            // Debit AP for the gross total
            if ($sumGross > 0) {
                $lines[] = $this->line($jeId, $date, $accAP, $ref, 'Reduce Accounts Payable (vendor owes less)', $sumGross, 0);
            }

            foreach ($lines as $l) DB::table('finance_account_entries')->insert($l);
            $this->assertBalanced($lines);

            return $jeId;
        });
    }

    /**
     * Split a debit amount into net + VAT using the original purchase line.
     * Falls back to "no VAT" if not available.
     *
     * Expected line_items columns for purchases/bills:
     *   total_unit_price, discount, vat (amount), amount (gross)
     */
    private function splitNetVatFromLineItem(string $model, int $docId, int $lineItemId, float $debitGross): array
    {
        $line = DB::table('line_items')
            ->where('id', $lineItemId)
            ->where('documentable_id', $docId)
            ->where('documentable_type', $model) // 'purchase_invoices' | 'vendor_bills'
            ->first();

        if (!$line) {
            return ['net' => $debitGross, 'vat' => 0.0];
        }

        $gross    = $this->col($line, 'amount', $debitGross);     // gross on the line
        $lineVAT  = $this->col($line, 'vat', null);               // VAT amount (not rate)
        $discount = $this->col($line, 'discount', 0.0);
        $unitTot  = $this->col($line, 'total_unit_price', null);  // qty * price (ex discount)

        // If VAT amount is present, allocate proportionally by gross
        if ($gross > 0 && $lineVAT !== null) {
            $p   = min(max($debitGross / (float)$gross, 0.0), 1.0);
            $vat = (float)$lineVAT * $p;
            $net = $debitGross - $vat;
            if ($net < 0) $net = 0.0;
            return ['net' => $net, 'vat' => $vat];
        }

        // If only unit/discount known, assume gross = net + VAT not known → treat as net
        return ['net' => $debitGross, 'vat' => 0.0];
    }

    /**
     * Figure out which account to CREDIT to reverse the original purchase line.
     * Priority: line.account_id → line.account_slug → fallback COGS.
     */
    private function resolveLineAccountForReversal(string $model, int $docId, int $lineItemId, int $companyId, int $fallbackAccId): int
    {
        $line = DB::table('line_items')
            ->where('id', $lineItemId)
            ->where('documentable_id', $docId)
            ->where('documentable_type', $model)
            ->first(['account_id','account_slug']);

        if ($line) {
            if (!empty($line->account_id)) {
                return (int)$line->account_id;
            }
            if (!empty($line->account_slug)) {
                $id = AccountHelper::id($line->account_slug, $companyId);
                if ($id) return (int)$id;
            }
        }
        return $fallbackAccId;
    }

    /**
     * If all items reference a single source doc, link the debit note JE to that JE.
     */
    private function fetchParentJournalEntryId(string $model, int $id): ?int
    {
        $table = $model; // 'purchase_invoices' or 'vendor_bills'
        if ($model === 'bills') $table = 'vendor_bills';

        return DB::table($table)->where('id', $id)->value('journal_entry_id') ?: null;
    }

    // ---- utilities ----

    private function normalizeModel(string $model): string
    {
        // Accept 'purchase_invoices' | 'vendor_bills' | 'bills'
        if ($model === 'bills') return 'vendor_bills';
        return $model;
    }

    private function col(object $row, string $name, $default)
    {
        return property_exists($row, $name) ? $row->{$name} : $default;
    }

    private function accountIdFromKeyOrSlug(string $key, string $fallbackSlug, int $companyId): int
    {
        $slug = config("accounts.$key") ?: $fallbackSlug;
        $id   = AccountHelper::id($slug, $companyId);
        if (!$id) {
            throw new \RuntimeException("Account not found for key/slug '$key' ('$slug') for company $companyId");
        }
        return (int)$id;
    }

    private function line(int $jeId, $date, int $accountId, string $ref, string $desc, float $debit, float $credit): array
    {
        return [
            'journal_entry_id' => $jeId,
            'date'             => $date,
            'account_id'       => $accountId,
            'reference'        => $ref,
            'description'      => $desc,
            'debit_amount'     => $debit,
            'credit_amount'    => $credit,
            'amount'           => $debit - $credit,
            'transaction_date' => $date,
            'edited_by'        => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
    }

    private function safeStr($value): string
    {
        return (string) ($value ?? '');
    }

    private function assertBalanced(array $lines): void
    {
        $d=0; $c=0;
        foreach ($lines as $l) { $d += $l['debit_amount']; $c += $l['credit_amount']; }
        if (abs($d - $c) > 0.0001) {
            throw new \RuntimeException('Debit Note journal not balanced.');
        }
    }
}
