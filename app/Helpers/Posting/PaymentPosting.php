<?php

namespace App\Helpers\Posting;

use Illuminate\Support\Facades\DB;
use App\Helpers\Posting\AccountHelper;
use App\Helpers\Posting\AccountProvisioner;

class PaymentPosting
{
    /**
     * Posts a payment against:
     *   - 'invoices'           (customer receipt):    Dr Cash/Bank  Cr AR
     *   - 'purchase_invoices'  (vendor payment):      Dr AP         Cr Cash/Bank
     *   - 'vendor_bills'       (vendor payment):      Dr AP         Cr Cash/Bank
     *
     * Returns journal_entry_id
     */
    public function post(array $payload): int
    {
        $modelTable   = $payload['model'];        // 'invoices' | 'purchase_invoices' | 'vendor_bills'
        $modelId      = (int)$payload['model_id'];
        $amount       = (float)$payload['amount_paid'];
        $date         = $payload['paid_on'] ?? now();
        $methodId     = $payload['payment_method_id'] ?? null; //payment_method_id is still bank_account_id

        // resolve the document (to grab company/customer/vendor, etc)
        $doc = DB::table($modelTable)->where('id', $modelId)->first();
        if (!$doc) {
            throw new \RuntimeException("{$modelTable} id={$modelId} not found.");
        }
        $companyId = (int)($doc->company_id ?? 0);
        $editedBy  = (int)($doc->created_by ?? null);

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be > 0');
        }

        // ✅ extra guard (in case someone bypasses FormRequest)
        $this->assertCashAndBankAccount($methodId, $companyId);

        // AR/AP accounts
        $accAR = AccountHelper::id(config('accounts.AR'), $companyId);
        $accAP = AccountHelper::id(config('accounts.AP'), $companyId);

        return DB::transaction(function () use (
            $modelTable,
            $modelId,
            $doc,
            $companyId,
            $editedBy,
            $amount,
            $date,
            $methodId,
            $accAR,
            $accAP
        ) {
            // header
            $type = match ($modelTable) {
                'invoices'          => 'customer_receipt',
                'purchase_invoices', 'vendor_bills' => 'vendor_payment',
                default => 'payment'
            };

            $info = match ($modelTable) {
                'invoices' => "Receipt for Invoice " . ($doc->invoiceID ?? $doc->id),
                'purchase_invoices' => "Payment for Purchase Invoice " . ($doc->purchase_invoiceID ?? $doc->id),
                'vendor_bills' => "Payment for Vendor Bill " . ($doc->id),
                default => "Payment for {$modelTable} {$doc->id}"
            };

            $jeId = DB::table('finance_journal_entries')->insertGetId([
                'date'       => $date,
                'info'       => $info,
                'edited_by'  => $editedBy,
                'status'     => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
                'company_id' => $companyId,
                'parent_journal_entry_id' => null,
                'type'       => $type,
            ]);

            // lines (balanced)
            $lines = [];
            if ($modelTable === 'invoices') {
                // Customer receipt: Dr Cash/Bank  Cr AR
                $lines[] = $this->line($jeId, $date, $methodId, 'Receipt', 'Cash/Bank (receipt)', $amount, 0);
                $lines[] = $this->line($jeId, $date, $accAR,              'Receipt', 'Reduce Accounts Receivable', 0, $amount);
            } else {
                // Vendor payment: Dr AP  Cr Cash/Bank
                $lines[] = $this->line($jeId, $date, $accAP,              'Payment', 'Reduce Accounts Payable', $amount, 0);
                $lines[] = $this->line($jeId, $date, $methodId, 'Payment', 'Cash/Bank (payment)', 0, $amount);
            }

            foreach ($lines as $l) {
                DB::table('finance_account_entries')->insert($l);
            }

            $this->assertBalanced($lines);

            //TODO: Update Model - Account Entries
            DB::table($modelTable)->where('id', $modelId)->update(['journal_entry_id' => $jeId]);
            // DB::table('payment_records')->where('id', $modelId)->update(['journal_entry_id' => $jeId]);

            return $jeId;
        });
    }



    /**
     * Idempotent sync: creates/refreshes the journal tied to a PaymentRecord.
     * - If payment_records.journal_entry_id exists, it clears & re-posts lines.
     * - Otherwise, it creates the header, inserts lines, and updates payment_records.journal_entry_id.
     *
     * Supports: invoices (customer receipt), purchase_invoices/vendor_bills (vendor payment).
     */
    public function syncForPaymentRecord(object $paymentRecord): int
    {
        // Pull needed fields
        $amount    = (float)($paymentRecord->amount_paid ?? 0);
        $date      = $paymentRecord->paid_on ?? now();


        //payment_method_id is still bank_account_id
        $methodId  = $paymentRecord->payment_method_id ?? null;
        $doc       = $paymentRecord->recordable;     // morph to Invoice / PurchaseInvoice / VendorBill
        if (!$doc) throw new \RuntimeException('Payment recordable not found.');

        $companyId = (int)($doc->company_id ?? 0);
        $editedBy  = (int)($paymentRecord->created_by ?? $doc->created_by ?? null);
        if ($amount <= 0) throw new \InvalidArgumentException('Payment amount must be > 0');

        // Type detection
        $docTable  = $doc->getTable(); // 'invoices' | 'purchase_invoices' | 'vendor_bills'
        $type = match ($docTable) {
            'invoices' => 'customer_receipt',
            'purchase_invoices', 'vendor_bills' => 'vendor_payment',
            default => 'payment'
        };

        // Resolve accounts
        $this->assertCashAndBankAccount($methodId, $companyId);

        $accAR = AccountHelper::id(config('accounts.AR'), $companyId);
        $accAP = AccountHelper::id(config('accounts.AP'), $companyId); // add 'AP' in config as suggested earlier

        return DB::transaction(function () use ($paymentRecord, $doc, $companyId, $editedBy, $date, $amount, $type, $docTable, $methodId, $accAR, $accAP) {

            // 1) Upsert header
            if (!empty($paymentRecord->journal_entry_id)) {
                $jeId = (int)$paymentRecord->journal_entry_id;

                DB::table('finance_journal_entries')->where('id', $jeId)->update([
                    'date'       => $date,
                    'info'       => $this->info($docTable, $doc),
                    'edited_by'  => $editedBy,
                    'status'     => 'posted',
                    'updated_at' => now(),
                    'type'       => $type,
                ]);

                DB::table('finance_account_entries')->where('journal_entry_id', $jeId)->delete();
            } else {
                $jeId = DB::table('finance_journal_entries')->insertGetId([
                    'date'       => $date,
                    'info'       => $this->info($docTable, $doc),
                    'edited_by'  => $editedBy,
                    'status'     => 'posted',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'company_id' => $companyId,
                    'parent_journal_entry_id' => $doc->journal_entry_id ?? null, // link to invoice journal if exists
                    'type'       => $type,
                ]);

                DB::table('payment_records')->where('id', $paymentRecord->id)->update(['journal_entry_id' => $jeId]);
                $paymentRecord->journal_entry_id = $jeId; // reflect on instance
            }

            // 2) Lines (balanced)
            $lines = [];
            if ($docTable === 'invoices') {
                // Customer receipt: Dr Cash/Bank  Cr AR
                $lines[] = $this->line($jeId, $date, $methodId, 'Receipt', 'Cash/Bank (receipt)', $amount, 0);
                $lines[] = $this->line($jeId, $date, $accAR,              'Receipt', 'Reduce Accounts Receivable', 0, $amount);
            } else {
                // Vendor payment: Dr AP  Cr Cash/Bank
                $lines[] = $this->line($jeId, $date, $accAP,              'Payment', 'Reduce Accounts Payable', $amount, 0);
                $lines[] = $this->line($jeId, $date, $methodId, 'Payment', 'Cash/Bank (payment)', 0, $amount);
            }

            foreach ($lines as $l) DB::table('finance_account_entries')->insert($l);
            $this->assertBalanced($lines);

            return $jeId;
        });
    }


    private function resolveSettlementAccountId(int $companyId, ?int $paymentMethodId): int
    {
        // try payment_methods.account_id if present
        if ($paymentMethodId) {
            $colExists = DB::getSchemaBuilder()->hasColumn('payment_methods', 'account_id');
            if ($colExists) {
                $aid = DB::table('payment_methods')->where('id', $paymentMethodId)->value('account_id');
                if ($aid) {
                    return (int)$aid;
                }
            }
            // or a slug column?
            $slugCol = DB::getSchemaBuilder()->hasColumn('payment_methods', 'account_slug');
            if ($slugCol) {
                $slug = DB::table('payment_methods')->where('id', $paymentMethodId)->value('account_slug');
                if ($slug) {
                    $id = AccountHelper::id($slug, $companyId);
                    if ($id) return (int)$id;
                }
            }
        }

        // fallback: ensure a CASH account under 'cash-and-bank', else use receipts clearing (suspense)
        $cashSlug = config('accounts.CASH_DEFAULT');
        $cashId = AccountHelper::id($cashSlug, $companyId);
        if (!$cashId) {
            $cashId = AccountProvisioner::ensure($companyId, $cashSlug, 'Cash', 'cash-and-bank');
        }
        return (int)($cashId ?: AccountHelper::id(config('accounts.RECEIPTS_CLR'), $companyId));
    }

    private function line(int $jeId, $date, int $accountId, string $ref, string $desc, float $debit, float $credit): array
    {
        if (!$accountId) throw new \InvalidArgumentException("Missing account for {$desc}");
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

    private function assertBalanced(array $lines): void
    {
        $d = 0;
        $c = 0;
        foreach ($lines as $l) {
            $d += $l['debit_amount'];
            $c += $l['credit_amount'];
        }
        if (abs($d - $c) > 0.0001) throw new \RuntimeException('Payment journal not balanced.');
    }

    private function assertCashAndBankAccount(int $accountId, int $companyId): void
    {
        $ok = DB::table('finance_chart_of_accounts as a')
            ->join('finance_account_sub_categories as sc', 'sc.id', '=', 'a.account_sub_category_id')
            ->where('a.id', $accountId)
            ->where('a.company_id', $companyId)
            ->where('sc.slug', 'cash-and-bank')
            ->exists();

        if (!$ok) {
            throw new \InvalidArgumentException('Selected bank account must be under Cash & Bank for this company.');
        }
    }

    private function info(string $docTable, object $doc): string
    {
        return match ($docTable) {
            'invoices'           => "Receipt for Invoice " . ($doc->invoiceID ?? $doc->id),
            'purchase_invoices'  => "Payment for Purchase Invoice " . ($doc->purchase_invoiceID ?? $doc->id),
            'vendor_bills'       => "Payment for Vendor Bill " . $doc->id,
            default              => "Payment for {$docTable} " . $doc->id
        };
    }
}
