<?php

namespace App\Services\Report;

use App\Models\Company;
use App\Models\FinanceAccountEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VatReturnService
{
    /**
     * Helper: net movement (credits - debits) for credit-nature accounts
     * (Income, Liabilities, Equity). Returns float.
     */
    protected function sumNetCredits(string $companyId, array $accountNumbers, Carbon $start, Carbon $end): float
    {
        return (float) FinanceAccountEntry::query()
            ->join('finance_chart_of_accounts as coa', 'finance_account_entries.account_id', '=', 'coa.id')
            ->where('coa.company_id', $companyId)
            ->whereIn('coa.account_number', $accountNumbers)
            ->whereBetween('finance_account_entries.transaction_date', [$start, $end])
            ->whereNull('finance_account_entries.deleted_at')
            ->selectRaw('COALESCE(SUM(credit_amount - debit_amount), 0) as net')
            ->value('net');
    }

    /**
     * Helper: net movement (debits - credits) for debit-nature accounts
     * (Assets, Expenses, contra-revenue). Returns float.
     */
    protected function sumNetDebits(string $companyId, array $accountNumbers, Carbon $start, Carbon $end): float
    {
        return (float) FinanceAccountEntry::query()
            ->join('finance_chart_of_accounts as coa', 'finance_account_entries.account_id', '=', 'coa.id')
            ->where('coa.company_id', $companyId)
            ->whereIn('coa.account_number', $accountNumbers)
            ->whereBetween('finance_account_entries.transaction_date', [$start, $end])
            ->whereNull('finance_account_entries.deleted_at')
            ->selectRaw('COALESCE(SUM(debit_amount - credit_amount), 0) as net')
            ->value('net');
    }

    /**
     * Generate STANDARD rate VAT Return for a given period (e.g., quarter).
     *
     *  Box 1: net movement on VAT Payable (liability)      -> credits - debits on 2040010008
     *  Box 4: net movement on Input VAT (asset)            -> debits - credits on 1020020004
     *  Box 6: net sales (EX VAT)                           -> Sales (4060010002) minus Discounts (4060010003) minus Returns (4060010004)
     *  Box 7: purchases (EX VAT)                           -> Expenses & Cost of Sales categories (7,8): debits - credits
     */
    public function generateStandardRateVatReturn(
        string $startDate,
        string $endDate,
        string $companyId,
        ?string $vatNumber = null,
        ?float $displayVatRate = null // purely informational (e.g., 7.5)
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->endOfDay();

        $company = Company::findOrFail($companyId);

        // Box 1: VAT due on sales & other outputs (liability ↑ = credit)
        $vatDueOnSales = $this->sumNetCredits($companyId, ['2040010008'], $start, $end);

        // Box 2: VAT on EC acquisitions (assume 0 for now)
        $vatEcAcquisitions = 0.0;

        // Box 3: Total output tax
        $totalOutputTax = $vatDueOnSales + $vatEcAcquisitions;

        // Box 4: VAT reclaimed on purchases (asset ↑ = debit)
        $vatReclaimed = $this->sumNetDebits($companyId, ['1020020004'], $start, $end);

        // Box 5: Net VAT to pay (or reclaim)
        $netVat = $totalOutputTax - $vatReclaimed;

        // Box 6: Net value of sales (EX VAT)
        //   Sales (credit-nature) less Discounts/Returns (debit-nature contra revenue)
        $salesNet      = $this->sumNetCredits($companyId, ['4060010002'], $start, $end); // Sales
        $discountsNet  = max(0.0, $this->sumNetDebits($companyId, ['4060010003'], $start, $end)); // Discounts
        $returnsNet    = max(0.0, $this->sumNetDebits($companyId, ['4060010004'], $start, $end)); // Sales return
        $netSales      = max(0.0, $salesNet - $discountsNet - $returnsNet);

        // Box 7: Net value of purchases (EX VAT)
        //   Expense & Cost-of-Sales categories -> debits - credits
        $netPurchases = (float) FinanceAccountEntry::query()
            ->join('finance_chart_of_accounts as coa', 'finance_account_entries.account_id', '=', 'coa.id')
            ->where('coa.company_id', $companyId)
            ->whereIn('coa.account_category_id', [7, 8]) // Operating expenses & Cost of Sales
            ->whereBetween('finance_account_entries.transaction_date', [$start, $end])
            ->whereNull('finance_account_entries.deleted_at')
            ->selectRaw('COALESCE(SUM(debit_amount - credit_amount), 0) as net')
            ->value('net');
        $netPurchases = max(0.0, $netPurchases);

        // Boxes 8 & 9 (EC supplies/acquisitions) -> 0 by assumption
        $ecSupplies     = 0.0;
        $ecAcquisitions = 0.0;

        return [
            'company_name'   => $company->name,
            'vat_number'     => $vatNumber,
            'quarter_ending' => $end->format('d.m.y'),
            'vat_rate'       => $displayVatRate, // for display only
            'line_total'     => number_format($netVat, 2, '.', ''),
            'lines' => [
                'line_1' => [
                    'value' => number_format($vatDueOnSales, 2, '.', ''),
                    'description' => 'VAT due on sales and other outputs',
                ],
                'line_2' => [
                    'value' => number_format($vatEcAcquisitions, 2, '.', ''),
                    'description' => 'VAT on EC acquisitions',
                ],
                'line_3' => [
                    'value' => number_format($totalOutputTax, 2, '.', ''),
                    'description' => 'Total output tax due',
                ],
                'line_4' => [
                    'value' => number_format($vatReclaimed, 2, '.', ''),
                    'description' => 'VAT reclaimed on purchases',
                ],
                'line_5' => [
                    'value' => number_format($netVat, 2, '.', ''),
                    'description' => 'Net VAT to pay (or reclaim)',
                ],
                'line_6' => [
                    'value' => number_format($netSales, 2, '.', ''),
                    'description' => 'Net value of sales (excluding VAT)',
                ],
                'line_7' => [
                    'value' => number_format($netPurchases, 2, '.', ''),
                    'description' => 'Net value of purchases (excluding VAT)',
                ],
                'line_8' => [
                    'value' => number_format($ecSupplies, 2, '.', ''),
                    'description' => 'EC supplies (net)',
                ],
                'line_9' => [
                    'value' => number_format($ecAcquisitions, 2, '.', ''),
                    'description' => 'EC acquisitions (net)',
                ],
            ],
            'debug' => [
                // Optional: keep for reconciliation during UAT; remove in prod.
                'sales_net'     => $salesNet,
                'discounts_net' => $discountsNet,
                'returns_net'   => $returnsNet,
            ],
        ];
    }

    /**
     * Generate FLAT rate VAT Return for a given period.
     *
     * Flat-rate VAT = flat_rate% * VAT-inclusive turnover.
     *
     * Since your invoices record NET sales (per corrected posting),
     * we gross-up net sales using the standard VAT rate to approximate
     * VAT-inclusive turnover:
     *      gross_turnover = net_sales * (1 + standardVatRate)
     *
     * If you truly store VAT-inclusive turnover elsewhere, you can
     * replace the gross-up with a direct sum of that account.
     *
     * @param float $flatRatePercent      e.g., 12.0 for 12% flat rate
     * @param float $standardVatRate      e.g., 20 for 20% (default UK)
     */
    public function generateFlatRateVatReturn(
        string $startDate,
        string $endDate,
        string $companyId,
        ?string $vatNumber = null,
        ?float $flatRatePercent = null,
        float $standardVatRate = 20 // adjust for your jurisdiction if needed
    ): array {
        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->endOfDay();

        $company = Company::findOrFail($companyId);

        if ($flatRatePercent === null || $flatRatePercent <= 0) {
            throw new \InvalidArgumentException('flatRatePercent must be a positive percentage, e.g., 12.0 for 12%.');
        }
        $flatRate = $flatRatePercent / 100.0; // convert to fraction

        // Net sales (EX VAT) as in standard method:
        $salesNet      = $this->sumNetCredits($companyId, ['4060010002'], $start, $end);
        $discountsNet  = max(0.0, $this->sumNetDebits($companyId, ['4060010003'], $start, $end));
        $returnsNet    = max(0.0, $this->sumNetDebits($companyId, ['4060010004'], $start, $end));
        $netSales      = max(0.0, $salesNet - $discountsNet - $returnsNet);

        // VAT-inclusive turnover: gross-up net sales using standard VAT rate
        $grossTurnover = $netSales * (1.0 + max(0.0, $standardVatRate));

        // Flat rate output tax (Box 1 under flat scheme)
        $vatDueOnSales     = $grossTurnover * $flatRate;
        $vatEcAcquisitions = 0.0;
        $totalOutputTax    = $vatDueOnSales + $vatEcAcquisitions;

        // Flat scheme generally does not reclaim input VAT (except for eligible capital items)
        $vatReclaimed = 0.0;

        // Net VAT payable
        $netVat = $totalOutputTax - $vatReclaimed;

        // Flat scheme Boxes 7/8/9 typically 0 for reporting (depends on local form)
        $netPurchases   = 0.0;
        $ecSupplies     = 0.0;
        $ecAcquisitions = 0.0;

        return [
            'company_name'          => $company->name,
            'vat_number'            => $vatNumber,
            'quarter_ending'        => $end->format('d.m.y'),
            'vat_rate' => $flatRatePercent,
            // 'flat_rate_percentage'  => $flatRatePercent,
            // 'standard_vat_rate'     => $standardVatRate, // for display // * 100
            'line_total'            => number_format($netVat, 2, '.', ''),
            'lines' => [
                'line_1' => [
                    'value' => number_format($vatDueOnSales, 2, '.', ''),
                    'description' => 'VAT due on sales and other outputs (flat rate)',
                ],
                'line_2' => [
                    'value' => number_format($vatEcAcquisitions, 2, '.', ''),
                    'description' => 'VAT on EC acquisitions',
                ],
                'line_3' => [
                    'value' => number_format($totalOutputTax, 2, '.', ''),
                    'description' => 'Total output tax due',
                ],
                'line_4' => [
                    'value' => number_format($vatReclaimed, 2, '.', ''),
                    'description' => 'VAT reclaimed on purchases (flat scheme)',
                ],
                'line_5' => [
                    'value' => number_format($netVat, 2, '.', ''),
                    'description' => 'Net VAT to pay (or reclaim)',
                ],
                'line_6' => [
                    'value' => number_format($grossTurnover, 2, '.', ''),
                    'description' => 'Gross value of sales (VAT-inclusive turnover)',
                ],
                'line_7' => [
                    'value' => number_format($netPurchases, 2, '.', ''),
                    'description' => 'Net value of purchases (flat scheme)',
                ],
                'line_8' => [
                    'value' => number_format($ecSupplies, 2, '.', ''),
                    'description' => 'EC supplies',
                ],
                'line_9' => [
                    'value' => number_format($ecAcquisitions, 2, '.', ''),
                    'description' => 'EC acquisitions',
                ],
            ],
            'debug' => [
                'sales_net'     => $salesNet,
                'discounts_net' => $discountsNet,
                'returns_net'   => $returnsNet,
            ],
        ];
    }
}


// <?php

// namespace App\Services\Report;

// use App\Models\Company;
// use App\Models\FinanceAccountEntry;
// use App\Models\FinanceChartOfAccount;
// use Carbon\Carbon;
// use Illuminate\Support\Facades\DB;

// class VatReturnService
// {
//     /**
//      * Generate Flat Rate VAT Return for a given quarter.
//      *
//      * @param string $startDate e.g., '2024-07-01'
//      * @param string $endDate e.g., '2024-09-30'
//      * @param string $companyName
//      * @param string $vatNumber
//      * @return array
//      */
//     public function generateFlatRateVatReturn(string $startDate, string $endDate, string $companyId, ?string $vatNumber = null, ?string $vatType = null): array
//     {
//         // Convert dates to Carbon for consistency
//         $start = Carbon::parse($startDate)->startOfDay();
//         $end = Carbon::parse($endDate)->endOfDay();

//         $company = Company::find($companyId);
//         $companyName = $company->name;

//         // line 6: Gross value of sales (VAT-inclusive)
//         $grossSales = FinanceAccountEntry::query()
//             ->join('finance_chart_of_accounts', 'finance_account_entries.account_id', '=', 'finance_chart_of_accounts.id')
//             ->where('finance_chart_of_accounts.account_number', '4060010001') // Sales Proceeds
//             ->where('finance_chart_of_accounts.company_id', $companyId)
//             ->whereBetween('finance_account_entries.transaction_date', [$start, $end])
//             ->whereNull('finance_account_entries.deleted_at')
//             ->sum('finance_account_entries.amount');

//         // line 1: VAT due on sales and other outputs
//         $vatDueOnSales = $grossSales * $vatType;

//         // line 2: VAT on EC acquisitions (assumed 0)
//         $vatEcAcquisitions = 0.0;

//         // line 3: Total output tax due
//         $totalOutputTax = $vatDueOnSales + $vatEcAcquisitions;

//         // line 4: VAT reclaimed on purchases (0 for Flat Rate, except capital goods > £2,000)
//         $vatReclaimed = 0.0;

//         // line 5: Net VAT to pay (or reclaim)
//         $netVat = $totalOutputTax - $vatReclaimed;

//         // line 7: Net value of purchases (0 for Flat Rate)
//         $netPurchases = 0.0;

//         // line 8 & 9: EC supplies and acquisitions (assumed 0)
//         $ecSupplies = 0.0;
//         $ecAcquisitions = 0.0;

//         return [
//             'company_name' => $companyName,
//             'vat_number' => $vatNumber,
//             'quarter_ending' => $end->format('d.m.y'),
//             'flat_rate_percentage' => $vatType * 100,
//             'line_total' => number_format($netVat, 2, '.', ''),
//             'lines' => [
//                 // 'line_1' => number_format($vatDueOnSales, 2, '.', ''),
//                 // 'line_2' => number_format($vatEcAcquisitions, 2, '.', ''),
//                 // 'line_3' => number_format($totalOutputTax, 2, '.', ''),
//                 // 'line_4' => number_format($vatReclaimed, 2, '.', ''),
//                 // 'line_5' => number_format($netVat, 2, '.', ''),
//                 // 'line_6' => number_format($grossSales, 2, '.', ''),
//                 // 'line_7' => number_format($netPurchases, 2, '.', ''),
//                 // 'line_8' => number_format($ecSupplies, 2, '.', ''),
//                 // 'line_9' => number_format($ecAcquisitions, 2, '.', ''),

//                 'line_1' => [
//                     'value' => number_format($vatDueOnSales, 2, '.', ''),
//                     'description' => 'VAT due on sales and other outputs'
//                 ],
//                 'line_2' => [
//                     'value' => number_format($vatEcAcquisitions, 2, '.', ''),
//                     'description' => 'VAT on EC acquisitions'
//                 ],
//                 'line_3' => [
//                     'value' => number_format($totalOutputTax, 2, '.', ''),
//                     'description' => 'Total output tax due'
//                 ],
//                 'line_4' => [
//                     'value' => number_format($vatReclaimed, 2, '.', ''),
//                     'description' => 'VAT reclaimed on purchases'
//                 ],
//                 'line_5' => [
//                     'value' => number_format($netVat, 2, '.', ''),
//                     'description' => 'Net VAT to pay (or reclaim)'
//                 ],
//                 'line_6' => [
//                     'value' => number_format($grossSales, 2, '.', ''),
//                     'description' => 'Gross value of sales (VAT-inclusive)'
//                 ],
//                 'line_7' => [
//                     'value' => number_format($netPurchases, 2, '.', ''),
//                     'description' => 'Net value of purchases'
//                 ],
//                 'line_8' => [
//                     'value' => number_format($ecSupplies, 2, '.', ''),
//                     'description' => 'EC supplies'
//                 ],
//                 'line_9' => [
//                     'value' => number_format($ecAcquisitions, 2, '.', ''),
//                     'description' => 'EC acquisitions'
//                 ],
//             ],
//         ];
//     }

//     /**
//      * Generate Standard Rate VAT Return for a given quarter.
//      *
//      * @param string $startDate e.g., '2024-07-01'
//      * @param string $endDate e.g., '2024-09-30'
//      * @param string $companyName
//      * @param string $vatNumber
//      * @return array
//      */
//     public function generateStandardRateVatReturn(string $startDate, string $endDate, string $companyId, ?string $vatNumber = null, ?int $vatRate = null): array
//     {
//         // Convert dates to Carbon for consistency
//         $start = Carbon::parse($startDate)->startOfDay();
//         $end = Carbon::parse($endDate)->endOfDay();

//         $company = Company::find($companyId);
//         $companyName = $company->name;

//         // line 1: VAT due on sales (from VAT Payable account)
//         $vatDueOnSales = FinanceAccountEntry::query()
//             ->join('finance_chart_of_accounts', 'finance_account_entries.account_id', '=', 'finance_chart_of_accounts.id')
//             ->where('finance_chart_of_accounts.account_number', '2040010008') // VAT Payable
//             ->where('finance_chart_of_accounts.company_id', $companyId)
//             ->whereBetween('finance_account_entries.transaction_date', [$start, $end])
//             ->whereNull('finance_account_entries.deleted_at')
//             ->sum('finance_account_entries.credit_amount');

//         // line 2: VAT on EC acquisitions (assumed 0)
//         $vatEcAcquisitions = 0.0;

//         // line 3: Total output tax due
//         $totalOutputTax = $vatDueOnSales + $vatEcAcquisitions;

//         // line 4: VAT reclaimed on purchases (from Input VAT account)
//         $vatReclaimed = FinanceAccountEntry::query()
//             ->join('finance_chart_of_accounts', 'finance_account_entries.account_id', '=', 'finance_chart_of_accounts.id')
//             ->where('finance_chart_of_accounts.account_number', '1020020004') // Input VAT
//             ->where('finance_chart_of_accounts.company_id', $companyId)
//             ->whereBetween('finance_account_entries.transaction_date', [$start, $end])
//             ->whereNull('finance_account_entries.deleted_at')
//             ->sum('finance_account_entries.debit_amount');

//         // line 5: Net VAT to pay (or reclaim)
//         $netVat = $totalOutputTax - $vatReclaimed;

//         // line 6: Gross value of sales (net of VAT)
//         $netSales = FinanceAccountEntry::query()
//             ->join('finance_chart_of_accounts', 'finance_account_entries.account_id', '=', 'finance_chart_of_accounts.id')
//             ->where('finance_chart_of_accounts.account_number', '4060010002') // Sales
//             ->where('finance_chart_of_accounts.company_id', $companyId)
//             ->whereBetween('finance_account_entries.transaction_date', [$start, $end])
//             ->whereNull('finance_account_entries.deleted_at')
//             ->sum('finance_account_entries.credit_amount');

//         // line 7: Net value of purchases (sum of relevant expense accounts)
//         $netPurchases = FinanceAccountEntry::query()
//             ->join('finance_chart_of_accounts', 'finance_account_entries.account_id', '=', 'finance_chart_of_accounts.id')
//             ->whereIn('finance_chart_of_accounts.account_category_id', [7, 8]) // Expenses and Cost of Sales
//             ->where('finance_chart_of_accounts.company_id', $companyId)
//             ->whereBetween('finance_account_entries.transaction_date', [$start, $end])
//             ->whereNull('finance_account_entries.deleted_at')
//             ->sum('finance_account_entries.debit_amount');

//         // line 8 & 9: EC supplies and acquisitions (assumed 0)
//         $ecSupplies = 0.0;
//         $ecAcquisitions = 0.0;

//         return [
//             'company_name' => $companyName,
//             'vat_number' => $vatNumber,
//             'quarter_ending' => $end->format('d.m.y'),
//             'vat_rate' => $vatRate,
//             'line_total' => number_format($netVat, 2, '.', ''),
//             'lines' => [
//                 // 'line_1' => number_format($vatDueOnSales, 2, '.', ''),
//                 // 'line_2' => number_format($vatEcAcquisitions, 2, '.', ''),
//                 // 'line_3' => number_format($totalOutputTax, 2, '.', ''),
//                 // 'line_4' => number_format($vatReclaimed, 2, '.', ''),
//                 // 'line_5' => number_format($netVat, 2, '.', ''),
//                 // 'line_6' => number_format($netSales, 2, '.', ''),
//                 // 'line_7' => number_format($netPurchases, 2, '.', ''),
//                 // 'line_8' => number_format($ecSupplies, 2, '.', ''),
//                 // 'line_9' => number_format($ecAcquisitions, 2, '.', ''),

//                 'line_1' => [
//                     'value' => number_format($vatDueOnSales, 2, '.', ''),
//                     'description' => 'VAT due on sales and other outputs'
//                 ],
//                 'line_2' => [
//                     'value' => number_format($vatEcAcquisitions, 2, '.', ''),
//                     'description' => 'VAT on EC acquisitions'
//                 ],
//                 'line_3' => [
//                     'value' => number_format($totalOutputTax, 2, '.', ''),
//                     'description' => 'Total output tax due'
//                 ],
//                 'line_4' => [
//                     'value' => number_format($vatReclaimed, 2, '.', ''),
//                     'description' => 'VAT reclaimed on purchases'
//                 ],
//                 'line_5' => [
//                     'value' => number_format($netVat, 2, '.', ''),
//                     'description' => 'Net VAT to pay (or reclaim)'
//                 ],
//                 'line_6' => [
//                     'value' => number_format($netSales, 2, '.', ''),
//                     'description' => 'Gross value of sales (VAT-inclusive)'
//                 ],
//                 'line_6' => [
//                     'value' => number_format($netSales, 2, '.', ''),
//                     'description' => 'Gross value of sales (VAT-inclusive)'
//                 ],
//                 'line_7' => [
//                     'value' => number_format($netPurchases, 2, '.', ''),
//                     'description' => 'Net value of purchases'
//                 ],
//                 'line_8' => [
//                     'value' => number_format($ecSupplies, 2, '.', ''),
//                     'description' => 'EC supplies'
//                 ],
//                 'line_9' => [
//                     'value' => number_format($ecAcquisitions, 2, '.', ''),
//                     'description' => 'EC acquisitions'
//                 ],
//             ]
//         ];
//     }
// }