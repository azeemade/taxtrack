<?php

namespace App\Services\FinanceAccountEntry;

use App\Models\FinanceAccountEntry;
use App\Models\FinanceChartOfAccount;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class FinanceAccountEntryService
{
    /**
     * Calculates the total balance, total inflow, and total outflow for the entire system.
     * 
     * @param string|null $startDate (YYYY-MM-DD)
     * @param string|null $endDate (YYYY-MM-DD)
     * @return array
     */
    public function getSystemTotalBalanceAndFlow(?string $startDate = null, ?string $endDate = null)
    {
        try {
            $query = FinanceAccountEntry::whereHas('journalEntry', function ($query) {
                $query->where('status', 'published')
                    ->where('company_id', auth()->user()->current_company_id);
            });

            // Apply date filter if provided
            if ($startDate && $endDate) {
                $query->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate]);
            }

            $entries = $query->get();

            // Initialize totals
            $totalSystemBalance = 0;
            $totalInflow = 0;
            $totalOutflow = 0;

            // Group entries by account to handle each account's type separately
            $entriesByAccount = $entries->groupBy('account_id');

            foreach ($entriesByAccount as $accountId => $accountEntries) {
                $account = FinanceChartOfAccount::with('accountType')->find($accountId);
                if (!$account || !$account->accountType) {
                    continue; // Skip if account or account type not found
                }

                $accountType = $account->accountType->slug;
                $totalDebit = $accountEntries->sum('debit_amount');
                $totalCredit = $accountEntries->sum('credit_amount');

                switch ($accountType) {
                    case 'asset':
                    case 'expense':
                        // Debit accounts: Balance = Debits - Credits
                        $accountBalance = $totalDebit - $totalCredit;
                        $totalInflow += $totalDebit;    // Inflow = increases (debits)
                        $totalOutflow += $totalCredit;  // Outflow = decreases (credits)
                        break;
                    case 'liability':
                    case 'equity':
                    case 'income':
                        // Credit accounts: Balance = Credits - Debits
                        $accountBalance = $totalCredit - $totalDebit;
                        $totalInflow += $totalCredit;  // Inflow = increases (credits)
                        $totalOutflow += $totalDebit;   // Outflow = decreases (debits)
                        break;
                    default:
                        // For unsupported types, just skip to next account
                        continue 2; // This continues the outer foreach loop
                }

                $totalSystemBalance += $accountBalance;
            }

            return [
                'total_system_balance' => $totalSystemBalance,
                'total_inflow' => $totalInflow,
                'total_outflow' => $totalOutflow,
                'currency' => 'USD', // Default, adjust as needed
            ];
        } catch (\Throwable $th) {
            throw new \Exception("Failed to calculate system totals: " . $th->getMessage());
        }
    }

    /**
     * Fetches current balance, total inflow, and total outflow for a specific account.
     *
     * @param int $accountId
     * @param string|null $startDate (YYYY-MM-DD)
     * @param string|null $endDate (YYYY-MM-DD)
     * @return array
     */
    public function getAccountBalanceWithFlow(int $accountId, ?string $startDate = null, ?string $endDate = null)
    {
        try {
            // Default to all-time if no date range provided
            $query = FinanceAccountEntry::where('account_id', $accountId)
                ->whereHas('journalEntry', function ($query) {
                    $query->where('status', 'published')
                        ->where('company_id', auth()->user()->current_company_id);
                });

            // Apply date filter if provided
            if ($startDate && $endDate) {
                $query->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate]);
            }

            // Fetch all entries for the account
            $entries = $query->get();

            // Calculate totals
            $totalDebit = $entries->sum('debit_amount');   // Total outflow (credits reduce assets)
            $totalCredit = $entries->sum('credit_amount');  // Total inflow (debits increase assets)

            // Determine account type (debit/credit normal balance)
            $account = FinanceChartOfAccount::with('accountType')->findOrFail($accountId);
            $accountTypeSlug = $account->accountType->slug;

            // Calculate current balance based on account type
            $currentBalance = 0;
            $totalInflow = 0;
            $totalOutflow = 0;

            switch ($accountTypeSlug) {
                case 'asset':
                case 'expense':
                    // Debit accounts: Balance = Total Debits - Total Credits
                    $currentBalance = $totalDebit - $totalCredit;
                    $totalInflow = $totalDebit;    // Inflow = increases (debits)
                    $totalOutflow = $totalCredit;  // Outflow = decreases (credits)
                    break;
                case 'liability':
                case 'equity':
                case 'income':
                    // Credit accounts: Balance = Total Credits - Total Debits
                    $currentBalance = $totalCredit - $totalDebit;
                    $totalInflow = $totalCredit;   // Inflow = increases (credits)
                    $totalOutflow = $totalDebit;   // Outflow = decreases (debits)
                    break;
                default:
                    throw new \Exception("Unsupported account type: {$accountTypeSlug}");
            }

            return [
                'current_balance' => $currentBalance,
                'total_inflow'    => $totalInflow,
                'total_outflow'   => $totalOutflow,
                'account_type'    => $accountTypeSlug,
                'currency'        => $account->currency->code ?? 'USD', // Adjust as needed
            ];
        } catch (\Throwable $th) {
            throw new \Exception("Failed to calculate account flow: " . $th->getMessage());
        }
    }
}
