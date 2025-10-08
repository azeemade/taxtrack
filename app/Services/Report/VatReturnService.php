<?php

namespace App\Services\Report;

use App\Models\Company;
use App\Models\FinanceAccountEntry;
use App\Models\FinanceChartOfAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VatReturnService
{
    /**
     * Generate Flat Rate VAT Return for a given quarter.
     *
     * @param string $startDate e.g., '2024-07-01'
     * @param string $endDate e.g., '2024-09-30'
     * @param string $companyName
     * @param string $vatNumber
     * @return array
     */
    public function generateFlatRateVatReturn(string $startDate, string $endDate, string $companyId, ?string $vatNumber = null, ?string $vatType = null): array
    {
        // Convert dates to Carbon for consistency
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $company = Company::find($companyId);
        $companyName = $company->name;

        // line 6: Gross value of sales (VAT-inclusive)
        $grossSales = FinanceAccountEntry::query()
            ->join('finance_chart_of_accounts', 'finance_account_entries.account_id', '=', 'finance_chart_of_accounts.id')
            ->where('finance_chart_of_accounts.account_number', '4060010001') // Sales Proceeds
            ->where('finance_chart_of_accounts.company_id', $companyId)
            ->whereBetween('finance_account_entries.transaction_date', [$start, $end])
            ->whereNull('finance_account_entries.deleted_at')
            ->sum('finance_account_entries.amount');

        // line 1: VAT due on sales and other outputs
        $vatDueOnSales = $grossSales * $vatType;

        // line 2: VAT on EC acquisitions (assumed 0)
        $vatEcAcquisitions = 0.0;

        // line 3: Total output tax due
        $totalOutputTax = $vatDueOnSales + $vatEcAcquisitions;

        // line 4: VAT reclaimed on purchases (0 for Flat Rate, except capital goods > £2,000)
        $vatReclaimed = 0.0;

        // line 5: Net VAT to pay (or reclaim)
        $netVat = $totalOutputTax - $vatReclaimed;

        // line 7: Net value of purchases (0 for Flat Rate)
        $netPurchases = 0.0;

        // line 8 & 9: EC supplies and acquisitions (assumed 0)
        $ecSupplies = 0.0;
        $ecAcquisitions = 0.0;

        return [
            'company_name' => $companyName,
            'vat_number' => $vatNumber,
            'quarter_ending' => $end->format('d.m.y'),
            'flat_rate_percentage' => $vatType * 100,
            'line_total' => number_format($netVat, 2, '.', ''),
            'lines' => [
                // 'line_1' => number_format($vatDueOnSales, 2, '.', ''),
                // 'line_2' => number_format($vatEcAcquisitions, 2, '.', ''),
                // 'line_3' => number_format($totalOutputTax, 2, '.', ''),
                // 'line_4' => number_format($vatReclaimed, 2, '.', ''),
                // 'line_5' => number_format($netVat, 2, '.', ''),
                // 'line_6' => number_format($grossSales, 2, '.', ''),
                // 'line_7' => number_format($netPurchases, 2, '.', ''),
                // 'line_8' => number_format($ecSupplies, 2, '.', ''),
                // 'line_9' => number_format($ecAcquisitions, 2, '.', ''),

                'line_1' => [
                    'value' => number_format($vatDueOnSales, 2, '.', ''),
                    'description' => 'VAT due on sales and other outputs'
                ],
                'line_2' => [
                    'value' => number_format($vatEcAcquisitions, 2, '.', ''),
                    'description' => 'VAT on EC acquisitions'
                ],
                'line_3' => [
                    'value' => number_format($totalOutputTax, 2, '.', ''),
                    'description' => 'Total output tax due'
                ],
                'line_4' => [
                    'value' => number_format($vatReclaimed, 2, '.', ''),
                    'description' => 'VAT reclaimed on purchases'
                ],
                'line_5' => [
                    'value' => number_format($netVat, 2, '.', ''),
                    'description' => 'Net VAT to pay (or reclaim)'
                ],
                'line_6' => [
                    'value' => number_format($grossSales, 2, '.', ''),
                    'description' => 'Gross value of sales (VAT-inclusive)'
                ],
                'line_7' => [
                    'value' => number_format($netPurchases, 2, '.', ''),
                    'description' => 'Net value of purchases'
                ],
                'line_8' => [
                    'value' => number_format($ecSupplies, 2, '.', ''),
                    'description' => 'EC supplies'
                ],
                'line_9' => [
                    'value' => number_format($ecAcquisitions, 2, '.', ''),
                    'description' => 'EC acquisitions'
                ],
            ],
        ];
    }

    /**
     * Generate Standard Rate VAT Return for a given quarter.
     *
     * @param string $startDate e.g., '2024-07-01'
     * @param string $endDate e.g., '2024-09-30'
     * @param string $companyName
     * @param string $vatNumber
     * @return array
     */
    public function generateStandardRateVatReturn(string $startDate, string $endDate, string $companyId, ?string $vatNumber = null, ?int $vatRate = null): array
    {
        // Convert dates to Carbon for consistency
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $company = Company::find($companyId);
        $companyName = $company->name;

        // line 1: VAT due on sales (from VAT Payable account)
        $vatDueOnSales = FinanceAccountEntry::query()
            ->join('finance_chart_of_accounts', 'finance_account_entries.account_id', '=', 'finance_chart_of_accounts.id')
            ->where('finance_chart_of_accounts.account_number', '2040010008') // VAT Payable
            ->where('finance_chart_of_accounts.company_id', $companyId)
            ->whereBetween('finance_account_entries.transaction_date', [$start, $end])
            ->whereNull('finance_account_entries.deleted_at')
            ->sum('finance_account_entries.credit_amount');

        // line 2: VAT on EC acquisitions (assumed 0)
        $vatEcAcquisitions = 0.0;

        // line 3: Total output tax due
        $totalOutputTax = $vatDueOnSales + $vatEcAcquisitions;

        // line 4: VAT reclaimed on purchases (from Input VAT account)
        $vatReclaimed = FinanceAccountEntry::query()
            ->join('finance_chart_of_accounts', 'finance_account_entries.account_id', '=', 'finance_chart_of_accounts.id')
            ->where('finance_chart_of_accounts.account_number', '1020020004') // Input VAT
            ->where('finance_chart_of_accounts.company_id', $companyId)
            ->whereBetween('finance_account_entries.transaction_date', [$start, $end])
            ->whereNull('finance_account_entries.deleted_at')
            ->sum('finance_account_entries.debit_amount');

        // line 5: Net VAT to pay (or reclaim)
        $netVat = $totalOutputTax - $vatReclaimed;

        // line 6: Gross value of sales (net of VAT)
        $netSales = FinanceAccountEntry::query()
            ->join('finance_chart_of_accounts', 'finance_account_entries.account_id', '=', 'finance_chart_of_accounts.id')
            ->where('finance_chart_of_accounts.account_number', '4060010002') // Sales
            ->where('finance_chart_of_accounts.company_id', $companyId)
            ->whereBetween('finance_account_entries.transaction_date', [$start, $end])
            ->whereNull('finance_account_entries.deleted_at')
            ->sum('finance_account_entries.credit_amount');

        // line 7: Net value of purchases (sum of relevant expense accounts)
        $netPurchases = FinanceAccountEntry::query()
            ->join('finance_chart_of_accounts', 'finance_account_entries.account_id', '=', 'finance_chart_of_accounts.id')
            ->whereIn('finance_chart_of_accounts.account_category_id', [7, 8]) // Expenses and Cost of Sales
            ->where('finance_chart_of_accounts.company_id', $companyId)
            ->whereBetween('finance_account_entries.transaction_date', [$start, $end])
            ->whereNull('finance_account_entries.deleted_at')
            ->sum('finance_account_entries.debit_amount');

        // line 8 & 9: EC supplies and acquisitions (assumed 0)
        $ecSupplies = 0.0;
        $ecAcquisitions = 0.0;

        return [
            'company_name' => $companyName,
            'vat_number' => $vatNumber,
            'quarter_ending' => $end->format('d.m.y'),
            'vat_rate' => $vatRate,
            'line_total' => number_format($netVat, 2, '.', ''),
            'lines' => [
                // 'line_1' => number_format($vatDueOnSales, 2, '.', ''),
                // 'line_2' => number_format($vatEcAcquisitions, 2, '.', ''),
                // 'line_3' => number_format($totalOutputTax, 2, '.', ''),
                // 'line_4' => number_format($vatReclaimed, 2, '.', ''),
                // 'line_5' => number_format($netVat, 2, '.', ''),
                // 'line_6' => number_format($netSales, 2, '.', ''),
                // 'line_7' => number_format($netPurchases, 2, '.', ''),
                // 'line_8' => number_format($ecSupplies, 2, '.', ''),
                // 'line_9' => number_format($ecAcquisitions, 2, '.', ''),

                'line_1' => [
                    'value' => number_format($vatDueOnSales, 2, '.', ''),
                    'description' => 'VAT due on sales and other outputs'
                ],
                'line_2' => [
                    'value' => number_format($vatEcAcquisitions, 2, '.', ''),
                    'description' => 'VAT on EC acquisitions'
                ],
                'line_3' => [
                    'value' => number_format($totalOutputTax, 2, '.', ''),
                    'description' => 'Total output tax due'
                ],
                'line_4' => [
                    'value' => number_format($vatReclaimed, 2, '.', ''),
                    'description' => 'VAT reclaimed on purchases'
                ],
                'line_5' => [
                    'value' => number_format($netVat, 2, '.', ''),
                    'description' => 'Net VAT to pay (or reclaim)'
                ],
                'line_6' => [
                    'value' => number_format($netSales, 2, '.', ''),
                    'description' => 'Gross value of sales (VAT-inclusive)'
                ],
                'line_6' => [
                    'value' => number_format($netSales, 2, '.', ''),
                    'description' => 'Gross value of sales (VAT-inclusive)'
                ],
                'line_7' => [
                    'value' => number_format($netPurchases, 2, '.', ''),
                    'description' => 'Net value of purchases'
                ],
                'line_8' => [
                    'value' => number_format($ecSupplies, 2, '.', ''),
                    'description' => 'EC supplies'
                ],
                'line_9' => [
                    'value' => number_format($ecAcquisitions, 2, '.', ''),
                    'description' => 'EC acquisitions'
                ],
            ]
        ];
    }
}