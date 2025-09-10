<?php

namespace App\Services\FinanceAccountType;

use App\Exceptions\BadRequestException;
use App\Helpers\AccountEntriesCalculationHelper;
use App\Helpers\FinanceTotalsHelper;
use App\Http\Requests\Company\Accounting\JournalEntry\JournalEntryRequest;
use App\Models\FinanceAccountEntry;
use App\Models\FinanceAccountSubCategory;
use App\Models\FinanceAccountType;
use App\Models\FinanceChartOfAccount;
use App\Models\FinanceJournalEntry;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class FinanceAccountTypeService
{
    public function dateRangeCalculatorOne($request)
    {
        $endDate = is_numeric($request->end_date) && strlen($request->end_date) === 4
            ? Carbon::create($request->end_date, 12, 31)->toDateString()
            : Carbon::parse($request->end_date)->toDateString();

        $startDate = Carbon::parse($endDate)->startOfYear()->toDateString();
        $pYEndDate = Carbon::parse($endDate)->subYear()->endOfYear()->toDateString();
        $pYStartDate = Carbon::parse($pYEndDate)->startOfYear()->toDateString();

        return [
            'currentYear' => Carbon::parse($endDate)->year,
            'previousYear' => Carbon::parse($pYEndDate)->year,
            'currentPeriod' => ['start' => $startDate, 'end' => $endDate],
            'previousPeriod' => ['start' => $pYStartDate, 'end' => $pYEndDate],
        ];
    }

    public function getProfitAndLossReport($request)
    {
        try {
            $export = $request->export; // pdf, excel

            // Determine dates
            $endDate = is_numeric($request->end_date) && strlen($request->end_date) === 4
                ? Carbon::create($request->end_date, 12, 31)->toDateString()
                : Carbon::parse($request->end_date)->toDateString();

            $startDate = Carbon::parse($endDate)->startOfYear()->toDateString();
            $pYEndDate = Carbon::parse($endDate)->subYear()->endOfYear()->toDateString();
            $pYStartDate = Carbon::parse($pYEndDate)->startOfYear()->toDateString();

            // Get revenue and expense account types
            $accountTypes = FinanceAccountType::whereIn("slug", ["income", "expense"])
                ->with([
                    'accountCategories.accountSubCategories.accounts.accountEntries' => function ($query) use ($startDate, $endDate, $pYStartDate, $pYEndDate) {
                        $query->whereHas('journalEntry', function ($query) {
                            $query->where('status', 'published')
                                ->where('company_id',  auth()->user()->current_company_id);
                        })
                            ->where(function ($query) use ($startDate, $endDate, $pYStartDate, $pYEndDate) {
                                $query->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate])
                                    ->orWhereBetween(DB::raw('DATE(date)'), [$pYStartDate, $pYEndDate]);
                            });
                    }
                ])
                ->get();

            $reportData = [
                'currentYear' => Carbon::parse($endDate)->year,
                'previousYear' => Carbon::parse($pYEndDate)->year,
                'currentPeriod' => ['start' => $startDate, 'end' => $endDate],
                'previousPeriod' => ['start' => $pYStartDate, 'end' => $pYEndDate],
                'categories' => [],
                'totalRevenueCurrent' => 0,
                'totalRevenuePrevious' => 0,
                'totalExpenseCurrent' => 0,
                'totalExpensePrevious' => 0,
                'netProfitCurrent' => 0,
                'netProfitPrevious' => 0,
            ];

            foreach ($accountTypes as $accountType) {
                $typeData = [
                    'name' => $accountType->name,
                    'slug' => $accountType->slug,
                    'totalCurrent' => 0,
                    'totalPrevious' => 0,
                    'categories' => [],
                ];

                foreach ($accountType->accountCategories as $category) {
                    $categoryData = [
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'totalCurrent' => 0,
                        'totalPrevious' => 0,
                        'subcategories' => [],
                    ];

                    foreach ($category->accountSubCategories as $subCategory) {
                        $subCategoryData = [
                            'name' => $subCategory->name,
                            'slug' => $subCategory->slug,
                            'totalCurrent' => 0,
                            'totalPrevious' => 0,
                        ];

                        foreach ($subCategory->accounts as $account) {
                            // Current year amounts
                            $cyDebit = $account->accountEntries
                                ->whereBetween('date', [$startDate, $endDate])
                                ->sum('debit_amount');
                            $cyCredit = $account->accountEntries
                                ->whereBetween('date', [$startDate, $endDate])
                                ->sum('credit_amount');

                            // Previous year amounts
                            $pyDebit = $account->accountEntries
                                ->whereBetween('date', [$pYStartDate, $pYEndDate])
                                ->sum('debit_amount');
                            $pyCredit = $account->accountEntries
                                ->whereBetween('date', [$pYStartDate, $pYEndDate])
                                ->sum('credit_amount');

                            // Determine balance based on account type
                            if ($accountType->slug === 'revenue') {
                                $cyBalance = $cyCredit - $cyDebit;
                                $pyBalance = $pyCredit - $pyDebit;
                            } else { // expense
                                $cyBalance = $cyDebit - $cyCredit;
                                $pyBalance = $pyDebit - $pyCredit;
                            }

                            $subCategoryData['totalCurrent'] += $cyBalance;
                            $subCategoryData['totalPrevious'] += $pyBalance;
                        }

                        $categoryData['subcategories'][] = $subCategoryData;
                        $categoryData['totalCurrent'] += $subCategoryData['totalCurrent'];
                        $categoryData['totalPrevious'] += $subCategoryData['totalPrevious'];
                    }

                    $typeData['categories'][] = $categoryData;
                    $typeData['totalCurrent'] += $categoryData['totalCurrent'];
                    $typeData['totalPrevious'] += $categoryData['totalPrevious'];
                }

                if ($accountType->slug === 'revenue') {
                    $reportData['totalRevenueCurrent'] = $typeData['totalCurrent'];
                    $reportData['totalRevenuePrevious'] = $typeData['totalPrevious'];
                } else {
                    $reportData['totalExpenseCurrent'] = $typeData['totalCurrent'];
                    $reportData['totalExpensePrevious'] = $typeData['totalPrevious'];
                }

                $reportData['categories'][] = $typeData;
            }

            // Calculate net profit
            $reportData['netProfitCurrent'] = $reportData['totalRevenueCurrent'] - $reportData['totalExpenseCurrent'];
            $reportData['netProfitPrevious'] = $reportData['totalRevenuePrevious'] - $reportData['totalExpensePrevious'];

            if ($export) {
                $fileName = 'profit_and_loss_' . now()->format('Ymd_His') . '.xlsx';

                return Excel::download(
                    new ProfitAndLossReportExport($reportData),
                    $fileName
                );
            }

            return $reportData;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function getIncomeReport($request)
    {
        try {
            $export = $request->export;

            $endDate = is_numeric($request->end_date) && strlen($request->end_date) === 4
                ? Carbon::create($request->end_date, 12, 31)->toDateString()
                : Carbon::parse($request->end_date)->toDateString();

            $startDate = Carbon::parse($endDate)->startOfYear()->toDateString();

            // Get revenue account type with monthly breakdown
            $revenueAccountType = FinanceAccountType::where("slug", "income")
                ->with([
                    'accountCategories.accountSubCategories.accounts' => function ($query) use ($startDate, $endDate) {
                        $query->with(['accountEntries' => function ($query) use ($startDate, $endDate) {
                            $query->whereHas('journalEntry', function ($query) {
                                $query->where('status', 'published')
                                    ->where('company_id',  auth()->user()->current_company_id);
                            })
                                ->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate]);
                        }]);
                    }
                ])
                ->first();

            // Prepare monthly data
            $months = [];
            $totalRevenue = 0;
            $categoryTotals = [];
            $currentMonth = Carbon::parse($startDate);
            $totalRevenue = 0;

            while ($currentMonth->lte(Carbon::parse($endDate))) {
                $monthStart = $currentMonth->copy()->startOfMonth();
                $monthEnd = $currentMonth->copy()->endOfMonth();

                $monthData = [
                    'name' => $currentMonth->format('F Y'),
                    'start' => $monthStart->toDateString(),
                    'end' => $monthEnd->toDateString(),
                    'total' => 0,
                    'categories' => [],
                ];

                foreach ($revenueAccountType->accountCategories as $category) {
                    $categoryData = [
                        'name' => $category->name,
                        'total' => 0,
                        'subcategories' => [],
                    ];

                    foreach ($category->accountSubCategories as $subCategory) {
                        $subCategoryData = [
                            'name' => $subCategory->name,
                            'total' => 0,
                            'accounts' => [],
                        ];

                        foreach ($subCategory->accounts as $account) {
                            $debit = $account->accountEntries
                                ->whereBetween('date', [$monthStart, $monthEnd])
                                ->sum('debit_amount');
                            $credit = $account->accountEntries
                                ->whereBetween('date', [$monthStart, $monthEnd])
                                ->sum('credit_amount');

                            $balance = $credit - $debit; // Revenue is credit - debit

                            $subCategoryData['accounts'][] = [
                                'name' => $account->name,
                                'amount' => $balance,
                            ];

                            $subCategoryData['total'] += $balance;
                        }

                        $categoryData['subcategories'][] = $subCategoryData;
                        $categoryData['total'] += $subCategoryData['total'];
                    }

                    // Track category totals across all months
                    if (!isset($categoryTotals[$category->name])) {
                        $categoryTotals[$category->name] = 0;
                    }
                    $categoryTotals[$category->name] += $categoryData['total'];

                    $monthData['categories'][] = $categoryData;
                    $monthData['total'] += $categoryData['total'];
                }

                $totalRevenue += $monthData['total'];
                $months[] = $monthData;
                $currentMonth->addMonth();
            }

            // Format category totals for response
            $formattedCategoryTotals = array_map(function ($name, $total) {
                return ['name' => $name, 'total' => $total];
            }, array_keys($categoryTotals), $categoryTotals);

            $reportData = [
                'year' => Carbon::parse($endDate)->year,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'months' => $months,
                'totalRevenue' => $totalRevenue,
                'categoryTotals' => $formattedCategoryTotals,
            ];

            if ($export) {
                $fileName = 'income_report_' . now()->format('Ymd_His') . '.xlsx';

                return Excel::download(
                    new IncomeReportExport($reportData),
                    $fileName
                );
            }

            return $reportData;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function getExpensesReport($request)
    {
        try {
            $export = $request->export;

            $endDate = is_numeric($request->end_date) && strlen($request->end_date) === 4
                ? Carbon::create($request->end_date, 12, 31)->toDateString()
                : Carbon::parse($request->end_date)->toDateString();

            $startDate = Carbon::parse($endDate)->startOfYear()->toDateString();

            // Load expense account data
            $expenseAccountType = FinanceAccountType::where("slug", "expense")
                ->with([
                    'accountCategories.accountSubCategories.accounts' => function ($query) use ($startDate, $endDate) {
                        $query->with(['accountEntries' => function ($query) use ($startDate, $endDate) {
                            $query->whereHas('journalEntry', function ($query) {
                                $query->where('status', 'published')
                                    ->where('company_id',  auth()->user()->current_company_id);
                            })->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate]);
                        }]);
                    }
                ])
                ->first();

            $months = [];
            $totalExpense = 0;
            $categoryTotals = []; // To store category-wise totals across all months
            $currentMonth = Carbon::parse($startDate);

            while ($currentMonth->lte(Carbon::parse($endDate))) {
                $monthStart = $currentMonth->copy()->startOfMonth();
                $monthEnd = $currentMonth->copy()->endOfMonth();

                $monthData = [
                    'name' => $currentMonth->format('F Y'),
                    'start' => $monthStart->toDateString(),
                    'end' => $monthEnd->toDateString(),
                    'total' => 0,
                    'categories' => [],
                ];

                foreach ($expenseAccountType->accountCategories as $category) {
                    $categoryData = [
                        'name' => $category->name,
                        'total' => 0,
                        'subcategories' => [],
                    ];

                    foreach ($category->accountSubCategories as $subCategory) {
                        $subCategoryData = [
                            'name' => $subCategory->name,
                            'total' => 0,
                            'accounts' => [],
                        ];

                        foreach ($subCategory->accounts as $account) {
                            $debit = $account->accountEntries
                                ->whereBetween('date', [$monthStart, $monthEnd])
                                ->sum('debit_amount');
                            $credit = $account->accountEntries
                                ->whereBetween('date', [$monthStart, $monthEnd])
                                ->sum('credit_amount');
                            $balance = $debit - $credit; // Expense is debit - credit

                            $subCategoryData['accounts'][] = [
                                'name' => $account->name,
                                'amount' => $balance,
                            ];

                            $subCategoryData['total'] += $balance;
                        }

                        $categoryData['subcategories'][] = $subCategoryData;
                        $categoryData['total'] += $subCategoryData['total'];
                    }

                    // Track category totals across all months
                    if (!isset($categoryTotals[$category->name])) {
                        $categoryTotals[$category->name] = 0;
                    }
                    $categoryTotals[$category->name] += $categoryData['total'];

                    $monthData['categories'][] = $categoryData;
                    $monthData['total'] += $categoryData['total'];
                }

                $totalExpense += $monthData['total'];
                $months[] = $monthData;
                $currentMonth->addMonth();
            }

            // Format category totals for response
            $formattedCategoryTotals = array_map(function ($name, $total) {
                return ['name' => $name, 'total' => $total];
            }, array_keys($categoryTotals), $categoryTotals);

            $reportData = [
                'year' => Carbon::parse($endDate)->year,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'months' => $months,
                'totalExpense' => $totalExpense,
                'categoryTotals' => $formattedCategoryTotals, // Add category totals to response
            ];

            if ($export) {
                $fileName = 'expenses_report_' . now()->format('Ymd_His') . '.xlsx';
                return Excel::download(
                    new ExpensesReportExport($reportData),
                    $fileName
                );
            }

            return $reportData;
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function getNetProfitMarginReport($request)
    {
        try {
            $export = $request->export ?? false;

            // Always get full year data (Jan-Dec)
            $year = is_numeric($request->end_date) && strlen($request->end_date) === 4
                ? (int)$request->end_date
                : Carbon::parse($request->end_date)->year;

            $startDate = Carbon::create($year, 1, 1)->startOfYear()->toDateString();
            $endDate = Carbon::create($year, 12, 31)->endOfYear()->toDateString();

            // Get revenue and expense account types for monthly breakdown
            $accountTypes = FinanceAccountType::whereIn("slug", ["revenue", "expense"])
                ->with([
                    'accountCategories.accountSubCategories.accounts' => function ($query) use ($startDate, $endDate) {
                        $query->with(['accountEntries' => function ($query) use ($startDate, $endDate) {
                            $query->whereHas('journalEntry', function ($query) {
                                $query->where('status', 'published')
                                    ->where('company_id',  auth()->user()->current_company_id);
                            })
                                ->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate]);
                        }]);
                    }
                ])
                ->get();

            // Initialize all months with zero values
            $months = collect(range(1, 12))->mapWithKeys(function ($month) use ($year) {
                $monthName = Carbon::create($year, $month, 1)->format('F Y');
                return [
                    $monthName => [
                        'name' => $monthName,
                        'revenue' => 0,
                        'expense' => 0,
                        'netProfit' => 0,
                        'margin' => 0,
                        'percentage' => 0,
                    ]
                ];
            })->toArray();

            $yearTotalRevenue = 0;
            $yearTotalExpense = 0;

            // Calculate values for each account
            foreach ($accountTypes as $accountType) {
                foreach ($accountType->accountCategories as $category) {
                    foreach ($category->accountSubCategories as $subCategory) {
                        foreach ($subCategory->accounts as $account) {
                            $entriesByMonth = $account->accountEntries
                                ->groupBy(function ($entry) {
                                    return Carbon::parse($entry->date)->format('F Y');
                                });

                            foreach ($entriesByMonth as $monthName => $entries) {
                                if (!isset($months[$monthName])) continue;

                                $debit = $entries->sum('debit_amount');
                                $credit = $entries->sum('credit_amount');

                                if ($accountType->slug === 'revenue') {
                                    $balance = $credit - $debit;
                                    $months[$monthName]['revenue'] += $balance;
                                } else {
                                    $balance = $debit - $credit;
                                    $months[$monthName]['expense'] += $balance;
                                }
                            }
                        }
                    }
                }
            }

            // Calculate net profit and margins for each month
            $months = array_map(function ($month) {
                $month['netProfit'] = $month['revenue'] - $month['expense'];
                $month['margin'] = $month['revenue'] != 0
                    ? round(($month['netProfit'] / $month['revenue']) * 100, 2)
                    : 0;
                $month['percentage'] = $month['margin']; // Alias for consistency
                return $month;
            }, $months);

            // Calculate yearly totals
            foreach ($months as $month) {
                $yearTotalRevenue += $month['revenue'];
                $yearTotalExpense += $month['expense'];
            }

            $yearNetProfit = $yearTotalRevenue - $yearTotalExpense;
            $yearMargin = $yearTotalRevenue != 0
                ? round(($yearNetProfit / $yearTotalRevenue) * 100, 2)
                : 0;

            $reportData = [
                'year' => $year,
                'months' => array_values($months), // Reset array keys
                'yearTotalRevenue' => $yearTotalRevenue,
                'yearTotalExpense' => $yearTotalExpense,
                'yearNetProfit' => $yearNetProfit,
                'yearMargin' => $yearMargin,
                'currentPercentage' => $yearMargin, // Current percentage (annual)
            ];

            if ($export) {
                $fileName = 'net_profit_margin_' . $year . '_' . now()->format('Ymd_His') . '.xlsx';
                return Excel::download(
                    new NetProfitMarginReportExport($reportData),
                    $fileName
                );
            }

            return $reportData;
        } catch (\Throwable $th) {
            return $th;
        }
    }

    public function getOperatingExpensesReport($request)
    {
        try {
            $export = $request->export;

            // Determine dates
            $endDate = is_numeric($request->end_date) && strlen($request->end_date) === 4
                ? Carbon::create($request->end_date, 12, 31)->toDateString()
                : Carbon::parse($request->end_date)->toDateString();

            $startDate = Carbon::parse($endDate)->startOfYear()->toDateString();
            $pYEndDate = Carbon::parse($endDate)->subYear()->endOfYear()->toDateString();
            $pYStartDate = Carbon::parse($pYEndDate)->startOfYear()->toDateString();

            // Get operating expenses accounts (under account subcategory with slug 'operating-expenses')
            $operatingExpenses = FinanceAccountSubCategory::where('slug', 'operating-expenses')
                ->with([
                    'accounts.accountEntries' => function ($query) use ($startDate, $endDate, $pYStartDate, $pYEndDate) {
                        $query->whereHas('journalEntry', function ($query) {
                            $query->where('status', 'published')
                                ->where('company_id',  auth()->user()->current_company_id);
                        })
                            ->where(function ($query) use ($startDate, $endDate, $pYStartDate, $pYEndDate) {
                                $query->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate])
                                    ->orWhereBetween(DB::raw('DATE(date)'), [$pYStartDate, $pYEndDate]);
                            });
                    },
                    'accounts.accountSubCategory.accountCategory'
                ])
                ->first();

            // If no operating expenses subcategory found
            if (!$operatingExpenses) {
                throw new BadRequestException("Operating sub-category not found!", Response::HTTP_NOT_FOUND);
            }

            $reportData = [
                'subcategoryName' => $operatingExpenses->name,
                'currentYear' => Carbon::parse($endDate)->year,
                'previousYear' => Carbon::parse($pYEndDate)->year,
                'currentPeriod' => ['start' => $startDate, 'end' => $endDate],
                'previousPeriod' => ['start' => $pYStartDate, 'end' => $pYEndDate],
                'accounts' => [],
                'totalCurrent' => 0,
                'totalPrevious' => 0,
            ];

            foreach ($operatingExpenses->accounts as $account) {
                // Current year amounts
                $cyDebit = $account->accountEntries
                    ->whereBetween('date', [$startDate, $endDate])
                    ->sum('debit_amount');
                $cyCredit = $account->accountEntries
                    ->whereBetween('date', [$startDate, $endDate])
                    ->sum('credit_amount');
                $cyBalance = $cyDebit - $cyCredit; // Expense is debit - credit

                // Previous year amounts
                $pyDebit = $account->accountEntries
                    ->whereBetween('date', [$pYStartDate, $pYEndDate])
                    ->sum('debit_amount');
                $pyCredit = $account->accountEntries
                    ->whereBetween('date', [$pYStartDate, $pYEndDate])
                    ->sum('credit_amount');
                $pyBalance = $pyDebit - $pyCredit;

                // Calculate percentage change
                $percentageChange = $pyBalance != 0
                    ? (($cyBalance - $pyBalance) / abs($pyBalance)) * 100
                    : ($cyBalance != 0 ? 100 : 0);

                $reportData['accounts'][] = [
                    'id' => $account->id,
                    'name' => $account->name,
                    'account_number' => $account->account_number,
                    'category' => $account->accountSubCategory->accountCategory->name,
                    'currentYear' => $cyBalance,
                    'previousYear' => $pyBalance,
                    'change' => $cyBalance - $pyBalance,
                    'percentageChange' => round($percentageChange, 2),
                    'isIncrease' => $cyBalance > $pyBalance,
                ];

                $reportData['totalCurrent'] += $cyBalance;
                $reportData['totalPrevious'] += $pyBalance;
            }

            // Calculate total percentage change
            $totalPercentageChange = $reportData['totalPrevious'] != 0
                ? (($reportData['totalCurrent'] - $reportData['totalPrevious']) / abs($reportData['totalPrevious'])) * 100
                : ($reportData['totalCurrent'] != 0 ? 100 : 0);

            $reportData['totalChange'] = $reportData['totalCurrent'] - $reportData['totalPrevious'];
            $reportData['totalPercentageChange'] = round($totalPercentageChange, 2);
            $reportData['isTotalIncrease'] = $reportData['totalCurrent'] > $reportData['totalPrevious'];

            // Sort accounts by highest current year amount (descending)
            usort($reportData['accounts'], function ($a, $b) {
                return $b['currentYear'] <=> $a['currentYear'];
            });

            if ($export) {
                $fileName = 'operating_expenses_' . now()->format('Ymd_His') . '.xlsx';

                return Excel::download(
                    new OperatingExpensesReportExport($reportData),
                    $fileName
                );
            }

            return $reportData;
        } catch (\Throwable $th) {
            return $th;
        }
    }

    public function getAverageTime($request)
    {
        try {
            $export = $request->export;

            // Determine dates
            $endDate = is_numeric($request->end_date) && strlen($request->end_date) === 4
                ? Carbon::create($request->end_date, 12, 31)->toDateString()
                : Carbon::parse($request->end_date)->toDateString();

            $startDate = Carbon::parse($endDate)->startOfYear()->toDateString();
            $pYEndDate = Carbon::parse($endDate)->subYear()->endOfYear()->toDateString();
            $pYStartDate = Carbon::parse($pYEndDate)->startOfYear()->toDateString();

            $reportData = [
                'reportDate' => [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'previousYearStartDate' => $pYStartDate,
                    'previousYearEndDate' => $pYEndDate,
                ],
                'averageDayToGetPaid' => self::averageDayToGetPaid(),
                'valueOfUnpaidInvoices' => self::valueOfUnpaidInvoices(),
                'averageDayToToPaySuppliers' => self::averageDayToToPaySuppliers(),
                'valueOfUnpaidBills' => self::valueOfUnpaidBills(),
            ];

            if ($export) {
                $fileName = 'average_days_' . now()->format('Ymd_His') . '.xlsx';

                return Excel::download(
                    new AverageTimeReportExport($reportData),
                    $fileName
                );
            }

            return $reportData;
        } catch (\Throwable $th) {
            return $th;
        }
    }


    public function getLiabilityToNetWorthRatio($request)
    {
        $year = $request->year ?? Carbon::now()->year;

        try {
            $monthlyData = [];

            for ($month = 1; $month <= 12; $month++) {
                $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                $endDate = Carbon::create($year, $month, 1)->endOfMonth();

                // Get total liabilities
                $liabilities = FinanceTotalsHelper::getAccountTypeTotal('liability', $startDate, $endDate);

                // Get total equity (net worth)
                $equity = FinanceTotalsHelper::getAccountTypeTotal('equity', $startDate, $endDate);

                // Calculate ratio
                $ratio = $equity != 0 ? $liabilities / $equity : 0;

                $monthlyData[] = [
                    'month' => $startDate->format('M Y'),
                    'total_liabilities' => $liabilities,
                    'total_equity' => $equity,
                    'ratio' => $ratio
                ];
            }

            // Current ratio (year-to-date)
            $currentLiabilities = FinanceTotalsHelper::getAccountTypeTotal('liability', Carbon::create($year, 1, 1), Carbon::now());
            $currentEquity = FinanceTotalsHelper::getAccountTypeTotal('equity', Carbon::create($year, 1, 1), Carbon::now());
            $currentRatio = $currentEquity != 0 ? $currentLiabilities / $currentEquity : 0;

            $reportData = [
                'year' => $year,
                'monthly_data' => $monthlyData,
                'current_ratio' => $currentRatio,
            ];

            return $reportData;
        } catch (\Throwable $th) {
            return $th;
        }
    }

    public function getDebtToEquityRatio($request)
    {
        $year = $request->year ?? Carbon::now()->year;

        try {
            $monthlyData = [];

            for ($month = 1; $month <= 12; $month++) {
                $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                $endDate = Carbon::create($year, $month, 1)->endOfMonth();

                // Get total debt (long-term liabilities)
                $debt = FinanceTotalsHelper::getAccountCategoryTotal('long-term-liabilities', $startDate, $endDate);

                // Get total equity (net worth)
                $equity = FinanceTotalsHelper::getAccountTypeTotal('equity', $startDate, $endDate);

                // Calculate ratio
                $ratio = $equity != 0 ? $debt / $equity : 0;

                $monthlyData[] = [
                    'month' => $startDate->format('M Y'),
                    'total_debt' => $debt,
                    'total_equity' => $equity,
                    'ratio' => $ratio
                ];
            }

            // Current ratio (year-to-date)
            $currentDebt = FinanceTotalsHelper::getAccountCategoryTotal('long-term-liabilities', Carbon::create($year, 1, 1), Carbon::now());
            $currentEquity = FinanceTotalsHelper::getAccountTypeTotal('equity', Carbon::create($year, 1, 1), Carbon::now());
            $currentRatio = $currentEquity != 0 ? $currentDebt / $currentEquity : 0;

            $reportData = [
                'year' => $year,
                'monthly_data' => $monthlyData,
                'current_ratio' => $currentRatio,
            ];

            return $reportData;
        } catch (\Throwable $th) {
            return $th;
        }
    }

    public function getFixedAssetToNetWorthRatio($request)
    {
        $year = $request->year ?? Carbon::now()->year;

        try {
            $monthlyData = [];

            for ($month = 1; $month <= 12; $month++) {
                $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                $endDate = Carbon::create($year, $month, 1)->endOfMonth();

                // Get fixed assets
                $fixedAssets = FinanceTotalsHelper::getAccountCategoryTotal('fixed-assets', $startDate, $endDate);

                // Get net worth (equity)
                $netWorth = FinanceTotalsHelper::getAccountTypeTotal('equity', $startDate, $endDate);

                // Calculate ratio
                $ratio = $netWorth != 0 ? $fixedAssets / $netWorth : 0;

                $monthlyData[] = [
                    'month' => $startDate->format('M Y'),
                    'fixed_assets' => $fixedAssets,
                    'net_worth' => $netWorth,
                    'ratio' => $ratio
                ];
            }

            // Current ratio (year-to-date)
            $currentFixedAssets = FinanceTotalsHelper::getAccountCategoryTotal('fixed-assets', Carbon::create($year, 1, 1), Carbon::now());
            $currentNetWorth = FinanceTotalsHelper::getAccountTypeTotal('equity', Carbon::create($year, 1, 1), Carbon::now());
            $currentRatio = $currentNetWorth != 0 ? $currentFixedAssets / $currentNetWorth : 0;

            $reportData = [
                'year' => $year,
                'monthly_data' => $monthlyData,
                'current_ratio' => $currentRatio,
            ];

            return $reportData;
        } catch (\Throwable $th) {
            return $th;
        }
    }

    public function getGrossProfitPercentage($request)
    {
        $year = $request->year ?? Carbon::now()->year;

        try {
            $monthlyData = [];

            for ($month = 1; $month <= 12; $month++) {
                $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                $endDate = Carbon::create($year, $month, 1)->endOfMonth();

                // Get revenue
                $revenue = FinanceTotalsHelper::getAccountCategoryTotal('revenue', $startDate, $endDate);

                // Get cost of goods sold
                $cogs = FinanceTotalsHelper::getAccountCategoryTotal('cost-of-goods-sold', $startDate, $endDate);

                // Calculate gross profit percentage
                $grossProfit = $revenue - $cogs;
                $percentage = $revenue != 0 ? ($grossProfit / $revenue) * 100 : 0;


                $monthlyData[] = [
                    'month' => $startDate->format('M Y'),
                    'revenue' => $revenue,
                    'cogs' => $cogs,
                    'gross_profit' => $grossProfit,
                    'percentage' => $percentage
                ];
            }

            // Current percentage (year-to-date)
            $currentRevenue = FinanceTotalsHelper::getAccountCategoryTotal('revenue', Carbon::create($year, 1, 1), Carbon::now());
            $currentCogs = FinanceTotalsHelper::getAccountCategoryTotal('cost-of-goods-sold', Carbon::create($year, 1, 1), Carbon::now());
            $currentGrossProfit = $currentRevenue - $currentCogs;
            $currentPercentage = $currentRevenue != 0 ? ($currentGrossProfit / $currentRevenue) * 100 : 0;


            $reportData = [
                'year' => $year,
                'monthly_data' => $monthlyData,
                'current_percentage' => $currentPercentage,
            ];

            return $reportData;
        } catch (\Throwable $th) {
            return $th;
        }
    }

    public function getNetProfitOnNetSales($request)
    {
        $year = $request->year ?? Carbon::now()->year;

        try {
            $monthlyData = [];

            for ($month = 1; $month <= 12; $month++) {
                $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                $endDate = Carbon::create($year, $month, 1)->endOfMonth();

                // Get net sales (revenue minus returns/allowances)
                $netSales = FinanceTotalsHelper::getAccountCategoryTotal('revenue', $startDate, $endDate)
                    - FinanceTotalsHelper::getAccountCategoryTotal('sales-returns', $startDate, $endDate)
                    - FinanceTotalsHelper::getAccountCategoryTotal('sales-allowances', $startDate, $endDate);

                // Get net profit (revenue - all expenses)
                $revenue = FinanceTotalsHelper::getAccountTypeTotal('income', $startDate, $endDate);
                $expenses = FinanceTotalsHelper::getAccountTypeTotal('expense', $startDate, $endDate);
                $netProfit = $revenue - $expenses;

                // Calculate percentage
                $percentage = $netSales != 0 ? ($netProfit / $netSales) * 100 : 0;

                $monthlyData[] = [
                    'month' => $startDate->format('M Y'),
                    'net_sales' => $netSales,
                    'net_profit' => $netProfit,
                    'percentage' => $percentage
                ];
            }

            // Current percentage (year-to-date)
            $currentNetSales = FinanceTotalsHelper::getAccountCategoryTotal('revenue', Carbon::create($year, 1, 1), Carbon::now())
                - FinanceTotalsHelper::getAccountCategoryTotal('sales-returns', Carbon::create($year, 1, 1), Carbon::now())
                - FinanceTotalsHelper::getAccountCategoryTotal('sales-allowances', Carbon::create($year, 1, 1), Carbon::now());

            $currentRevenue = FinanceTotalsHelper::getAccountTypeTotal('income', Carbon::create($year, 1, 1), Carbon::now());
            $currentExpenses = FinanceTotalsHelper::getAccountTypeTotal('expense', Carbon::create($year, 1, 1), Carbon::now());
            $currentNetProfit = $currentRevenue - $currentExpenses;
            $currentPercentage = $currentNetSales != 0 ? ($currentNetProfit / $currentNetSales) * 100 : 0;

            $reportData = [
                'year' => $year,
                'monthly_data' => $monthlyData,
                'current_percentage' => $currentPercentage,
            ];

            return $reportData;
        } catch (\Throwable $th) {
            return $th;
        }
    }

    public function getWorkingCapitalToTotalAssets($request)
    {
        $year = $request->year ?? Carbon::now()->year;

        try {
            $monthlyData = [];

            for ($month = 1; $month <= 12; $month++) {
                $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                $endDate = Carbon::create($year, $month, 1)->endOfMonth();

                // Get current assets
                $currentAssets = FinanceTotalsHelper::getAccountCategoryTotal('current-assets', $startDate, $endDate);

                // Get current liabilities
                $currentLiabilities = FinanceTotalsHelper::getAccountCategoryTotal('current-liabilities', $startDate, $endDate);

                // Get total assets
                $totalAssets = FinanceTotalsHelper::getAccountTypeTotal('asset', $startDate, $endDate);

                // Calculate working capital and ratio
                $workingCapital = $currentAssets - $currentLiabilities;
                $ratio = $totalAssets != 0 ? $workingCapital / $totalAssets : 0;

                $monthlyData[] = [
                    'month' => $startDate->format('M Y'),
                    'current_assets' => $currentAssets,
                    'current_liabilities' => $currentLiabilities,
                    'working_capital' => $workingCapital,
                    'total_assets' => $totalAssets,
                    'ratio' => $ratio
                ];
            }

            // Current ratio (year-to-date)
            $currentCurrentAssets = FinanceTotalsHelper::getAccountCategoryTotal('current-assets', Carbon::create($year, 1, 1), Carbon::now());
            $currentCurrentLiabilities = FinanceTotalsHelper::getAccountCategoryTotal('current-liabilities', Carbon::create($year, 1, 1), Carbon::now());
            $currentWorkingCapital = $currentCurrentAssets - $currentCurrentLiabilities;
            $currentTotalAssets = FinanceTotalsHelper::getAccountTypeTotal('asset', Carbon::create($year, 1, 1), Carbon::now());
            $currentRatio = $currentTotalAssets != 0 ? $currentWorkingCapital / $currentTotalAssets : 0;

            $reportData = [
                'year' => $year,
                'monthly_data' => $monthlyData,
                'current_ratio' => $currentRatio,
            ];

            return $reportData;
        } catch (\Throwable $th) {
            return $th;
        }
    }

    public function getBalanceSheetReport($request)
    {
        try {
            $export = $request->export;

            $endDate = is_numeric($request->end_date) && strlen($request->end_date) === 4
                ? Carbon::create($request->end_date, 12, 31)->toDateString()
                : Carbon::parse($request->end_date)->toDateString();

            $startDate = Carbon::parse($endDate)->startOfYear()->toDateString();
            $pYEndDate = Carbon::parse($endDate)->subYear()->endOfYear()->toDateString();

            $accountTypes = FinanceAccountType::whereIn("slug", ["asset", "liability", "equity"])
                ->with([
                    'accountCategories.accountSubCategories.accounts.accountEntries' => function ($query) use ($startDate, $endDate, $pYEndDate) {
                        $query->whereHas('journalEntry', function ($query) {
                            $query->where('status', 'published')
                                ->where('company_id',  auth()->user()->current_company_id);
                        })
                            ->where(function ($query) use ($startDate, $endDate, $pYEndDate) {
                                $query->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate])
                                    ->orWhereBetween(DB::raw('DATE(date)'), [$startDate, $pYEndDate]);
                            });
                    }
                ])
                ->get();

            $balanceSheet = [];
            $totals = [
                'assetCurrent' => 0,
                'assetPrevious' => 0,
                'liabilityCurrent' => 0,
                'liabilityPrevious' => 0,
                'equityCurrent' => 0,
                'equityPrevious' => 0,
            ];

            foreach ($accountTypes as $accountType) {
                $accountTypeData = [
                    'name' => $accountType->name,
                    'slug' => $accountType->slug,
                    'totalCurrent' => 0,
                    'totalPrevious' => 0,
                    'categories' => [],
                ];

                foreach ($accountType->accountCategories as $accountCategory) {
                    $accountCategoryData = [
                        'name' => $accountCategory->name,
                        'totalCurrent' => 0,
                        'totalPrevious' => 0,
                        'subcategories' => [],
                    ];

                    foreach ($accountCategory->accountSubCategories as $accountSubCategory) {
                        $accountSubCategoryData = [
                            'name' => $accountSubCategory->name,
                            'current' => 0,
                            'previous' => 0,
                        ];

                        foreach ($accountSubCategory->accounts as $account) {
                            // Current year balances
                            $cyDebit = $account->accountEntries
                                ->whereBetween('date', [$startDate, $endDate])
                                ->sum('debit_amount');
                            $cyCredit = $account->accountEntries
                                ->whereBetween('date', [$startDate, $endDate])
                                ->sum('credit_amount');

                            // Previous year balances
                            $pyDebit = $account->accountEntries
                                ->whereBetween('date', [$startDate, $pYEndDate])
                                ->sum('debit_amount');
                            $pyCredit = $account->accountEntries
                                ->whereBetween('date', [$startDate, $pYEndDate])
                                ->sum('credit_amount');

                            // Determine balance based on account type
                            if ($accountType->slug === 'asset') {
                                $cyBalance = $cyDebit - $cyCredit;
                                $pyBalance = $pyDebit - $pyCredit;
                            } else { // liability or equity
                                $cyBalance = $cyCredit - $cyDebit;
                                $pyBalance = $pyCredit - $pyDebit;
                            }

                            $accountSubCategoryData['current'] += $cyBalance;
                            $accountSubCategoryData['previous'] += $pyBalance;
                        }

                        $accountCategoryData['subcategories'][] = $accountSubCategoryData;
                        $accountCategoryData['totalCurrent'] += $accountSubCategoryData['current'];
                        $accountCategoryData['totalPrevious'] += $accountSubCategoryData['previous'];
                    }

                    $accountTypeData['categories'][] = $accountCategoryData;
                    $accountTypeData['totalCurrent'] += $accountCategoryData['totalCurrent'];
                    $accountTypeData['totalPrevious'] += $accountCategoryData['totalPrevious'];
                }

                // Update totals
                if ($accountType->slug === 'asset') {
                    $totals['assetCurrent'] = $accountTypeData['totalCurrent'];
                    $totals['assetPrevious'] = $accountTypeData['totalPrevious'];
                } elseif ($accountType->slug === 'liability') {
                    $totals['liabilityCurrent'] = $accountTypeData['totalCurrent'];
                    $totals['liabilityPrevious'] = $accountTypeData['totalPrevious'];
                } else {
                    $totals['equityCurrent'] = $accountTypeData['totalCurrent'];
                    $totals['equityPrevious'] = $accountTypeData['totalPrevious'];
                }

                $balanceSheet[] = $accountTypeData;
            }

            $reportData = [
                'year' => Carbon::parse($endDate)->year,
                'previousYear' => Carbon::parse($pYEndDate)->year,
                'balanceSheet' => $balanceSheet,
                'totals' => $totals,
            ];

            if ($export) {
                $fileName = 'balance_sheet_' . now()->format('Ymd_His') . '.xlsx';

                return Excel::download(
                    new BalanceSheetReportExport($reportData),
                    $fileName
                );
            }

            return $reportData;
        } catch (\Throwable $th) {
            return $th;
        }
    }

    public function getCashBalancesReport($request)
    {
        try {
            $export = $request->export;

            $endDate = is_numeric($request->end_date) && strlen($request->end_date) === 4
                ? Carbon::create($request->end_date, 12, 31)->toDateString()
                : Carbon::parse($request->end_date)->toDateString();

            $startDate = Carbon::parse($endDate)->startOfYear()->toDateString();
            $pYEndDate = Carbon::parse($endDate)->subYear()->endOfYear()->toDateString();

            // Get cash accounts (assuming they are under asset type with a cash category)
            $cashAccounts = FinanceChartOfAccount::whereHas('subCategory', function ($query) {
                $query->where('slug', 'cash-and-bank')
                    ->whereHas('accountType', function ($query) {
                        $query->where('slug', 'asset');
                    });
            })
                ->with(['accountEntries' => function ($query) use ($startDate, $endDate, $pYEndDate) {
                    $query->whereHas('journalEntry', function ($query) {
                        $query->where('status', 'published')
                            ->where('company_id',  auth()->user()->current_company_id);
                    })
                        ->where(function ($query) use ($startDate, $endDate, $pYEndDate) {
                            $query->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate])
                                ->orWhereBetween(DB::raw('DATE(date)'), [$startDate, $pYEndDate]);
                        });
                }])
                ->get();

            $reportData = [
                'year' => Carbon::parse($endDate)->year,
                'previousYear' => Carbon::parse($pYEndDate)->year,
                'accounts' => [],
                'totalCurrent' => 0,
                'totalPrevious' => 0,
            ];

            foreach ($cashAccounts as $account) {
                // Current year balances
                $cyDebit = $account->accountEntries
                    ->whereBetween('date', [$startDate, $endDate])
                    ->sum('debit_amount');
                $cyCredit = $account->accountEntries
                    ->whereBetween('date', [$startDate, $endDate])
                    ->sum('credit_amount');
                $cyBalance = $cyDebit - $cyCredit; // Asset is debit - credit

                // Previous year balances
                $pyDebit = $account->accountEntries
                    ->whereBetween('date', [$startDate, $pYEndDate])
                    ->sum('debit_amount');
                $pyCredit = $account->accountEntries
                    ->whereBetween('date', [$startDate, $pYEndDate])
                    ->sum('credit_amount');
                $pyBalance = $pyDebit - $pyCredit;

                $reportData['accounts'][] = [
                    'name' => $account->name,
                    'current' => $cyBalance,
                    'previous' => $pyBalance,
                ];

                $reportData['totalCurrent'] += $cyBalance;
                $reportData['totalPrevious'] += $pyBalance;
            }

            if ($export) {
                $fileName = 'cash_balances_' . now()->format('Ymd_His') . '.xlsx';

                return Excel::download(
                    new CashBalancesReportExport($reportData),
                    $fileName
                );
            }

            return $reportData;
        } catch (\Throwable $th) {
            return $th;
        }
    }

    public function getBalanceSheetMajorRunAtDate($request)
    {
        try {
            $export = $request->export; // pdf, excel
            $currentDate = Carbon::now()->format('d/M/Y');

            // Parse primary date filter
            $primaryDate = $this->parseDateFilter($request->primary_date, $request->primary_period_type);
            $endDate = $primaryDate['end_date'];
            $startDate = $primaryDate['start_date'];

            // Parse comparison date filter if provided
            $comparisonDate = null;
            if ($request->has('compare_with') && $request->compare_with !== 'none') {
                $comparisonDate = $this->parseComparisonDate($endDate, $request->compare_with, $request->compare_period, $request->compare_value);
                $pYEndDate = $comparisonDate['end_date'];
                $pYStartDate = $comparisonDate['start_date'];
            } else {
                // Default to previous year if no comparison selected
                $pYEndDate = Carbon::parse($endDate)->subYear()->endOfYear()->toDateString();
                $pYStartDate = Carbon::parse($pYEndDate)->startOfYear()->toDateString();
            }

            // Get account types with their balances
            $accountTypes = FinanceAccountType::whereIn("slug", ["asset", "liability", "equity"])
                ->with([
                    'accountCategories.accountSubCategories.accounts.accountEntries' => function ($query) use ($startDate, $endDate, $pYStartDate, $pYEndDate) {
                        $query->whereHas('journalEntry', function ($query) {
                            $query->where('status', 'published')
                                ->where('company_id',  auth()->user()->current_company_id);
                        })
                            ->where(function ($query) use ($startDate, $endDate, $pYStartDate, $pYEndDate) {
                                $query->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate])
                                    ->orWhereBetween(DB::raw('DATE(date)'), [$pYStartDate, $pYEndDate]);
                            });
                    },
                    'accountCategories.accountSubCategories.accounts.accountEntries.journalEntry:id,status'
                ])
                ->get();

            // Calculate retained earnings for both periods
            $currentRetainedEarnings = AccountEntriesCalculationHelper::calculateRetainedEarningsByDate($startDate, $endDate);
            $previousRetainedEarnings = AccountEntriesCalculationHelper::calculateRetainedEarningsByDate($pYStartDate, $pYEndDate);

            // Process balance sheet data
            $balanceSheet = $this->processBalanceSheetData(
                $accountTypes,
                $startDate,
                $endDate,
                $pYStartDate,
                $pYEndDate,
                $currentRetainedEarnings,
                $previousRetainedEarnings
            );

            // Prepare response
            $record = [
                'balanceSheet' => $balanceSheet,
                'totalAssetCy' => $balanceSheet[0]['totalCyBalance'] ?? 0,
                'totalAssetPy' => $balanceSheet[0]['totalPyBalance'] ?? 0,
                'totalLiabilityCy' => $balanceSheet[1]['totalCyBalance'] ?? 0,
                'totalLiabilityPy' => $balanceSheet[1]['totalPyBalance'] ?? 0,
                'totalEquityCy' => $balanceSheet[2]['totalCyBalance'] ?? 0,
                'totalEquityPy' => $balanceSheet[2]['totalPyBalance'] ?? 0,
                'equityLiabilityCyTotal' => ($balanceSheet[1]['totalCyBalance'] ?? 0) + ($balanceSheet[2]['totalCyBalance'] ?? 0),
                'equityLiabilityPyTotal' => ($balanceSheet[1]['totalPyBalance'] ?? 0) + ($balanceSheet[2]['totalPyBalance'] ?? 0),
                'currentDate' => $currentDate,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'previousStartDate' => $pYStartDate,
                'previousEndDate' => $pYEndDate,
                'currentYear' => Carbon::parse($endDate)->year,
                'previousYear' => Carbon::parse($pYEndDate)->year,
                'comparisonDate' => $comparisonDate,
                'filterDescription' => $this->getFilterDescription($request),
                'comparisonDescription' => $comparisonDate ? $this->getComparisonDescription($request) : null,
            ];

            // Handle exports
            if ($export) {
                $fileName = 'balance_sheet_' . now()->format('Ymd_His') . '.xlsx';

                return Excel::download(
                    new BalanceSheetFirstLevelReportExportCYPY($record),
                    $fileName
                );
            }

            return $record;
        } catch (\Throwable $th) {
            return $th;
        }
    }

    public function getCashSummaryReport($request)
    {
        try {
            $export = $request->export ?? false;
            $rangeType = $request->range_type ?? 'monthly';
            $year = $request->year ?? now()->year;
            $month = $request->month ?? null;
            $quarter = $request->quarter ?? null;
            $customStart = $request->custom_start ?? null;
            $customEnd = $request->custom_end ?? null;

            // Determine date range based on selected type
            switch ($rangeType) {
                case 'yearly':
                    $startDate = Carbon::create($year, 1, 1)->startOfYear();
                    $endDate = Carbon::create($year, 12, 31)->endOfYear();
                    $periodName = "Year {$year}";
                    // Create all months in the year
                    $periods = collect(range(1, 12))->map(function ($month) use ($year) {
                        $date = Carbon::create($year, $month, 1);
                        return [
                            'name' => $date->format('F Y'),
                            'start_date' => $date->copy()->startOfMonth()->toDateString(),
                            'end_date' => $date->copy()->endOfMonth()->toDateString()
                        ];
                    });
                    break;

                case 'quarterly':
                    $quarter = $quarter ?? ceil(now()->month / 3);
                    $startMonth = ($quarter - 1) * 3 + 1;
                    $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
                    $endDate = $startDate->copy()->addMonths(2)->endOfMonth();
                    $periodName = "Q{$quarter} {$year}";
                    // Create all months in the quarter
                    $periods = collect(range(0, 2))->map(function ($offset) use ($startDate) {
                        $date = $startDate->copy()->addMonths($offset);
                        return [
                            'name' => $date->format('F Y'),
                            'start_date' => $date->copy()->startOfMonth()->toDateString(),
                            'end_date' => $date->copy()->endOfMonth()->toDateString()
                        ];
                    });
                    break;

                case 'monthly':
                    $month = $month ?? now()->month;
                    $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                    $endDate = $startDate->copy()->endOfMonth();
                    $periodName = $startDate->format('F Y');
                    $periods = [[
                        'name' => $periodName,
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString()
                    ]];
                    break;

                case 'custom_dates':
                    $startDate = Carbon::parse($customStart)->startOfDay();
                    $endDate = Carbon::parse($customEnd)->endOfDay();
                    $periodName = $startDate->format('M d, Y') . ' to ' . $endDate->format('M d, Y');

                    // Create all months in the custom range
                    $periods = collect();
                    $current = $startDate->copy()->startOfMonth();

                    while ($current <= $endDate) {
                        $periodStart = $current->copy()->max($startDate);
                        $periodEnd = $current->copy()->endOfMonth()->min($endDate);

                        $periods->push([
                            'name' => $current->format('F Y'),
                            'start_date' => $periodStart->toDateString(),
                            'end_date' => $periodEnd->toDateString()
                        ]);

                        $current->addMonth();
                    }
                    break;

                // Other range types (MTD, QTD, YTD, etc.) can be added similarly
                default:
                    // Default to current month
                    $startDate = now()->startOfMonth();
                    $endDate = now()->endOfMonth();
                    $periodName = now()->format('F Y');
                    $periods = [[
                        'name' => $periodName,
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString()
                    ]];
            }

            // Get cash and bank accounts with their entries for the entire date range
            $cashSubCategory = FinanceAccountSubCategory::where('slug', 'cash-and-bank')
                ->with([
                    'accounts' => function ($query) use ($startDate, $endDate) {
                        $query->with(['accountEntries' => function ($query) use ($startDate, $endDate) {
                            $query->whereHas('journalEntry', function ($query) {
                                $query->where('status', 'published')
                                    ->where('company_id', auth()->user()->current_company_id);
                            })
                                ->whereBetween('date', [$startDate, $endDate]);
                        }]);
                    }
                ])
                ->first();

            // Initialize report data structure
            $reportData = [
                'range_type' => $rangeType,
                'period_name' => $periodName,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'months' => [],
                'totals' => [
                    'opening_balance' => 0,
                    'total_inflows' => 0,
                    'total_outflows' => 0,
                    'net_change' => 0,
                    'closing_balance' => 0,
                ]
            ];

            if ($cashSubCategory) {
                // Process each account to calculate opening balances (before the start date)
                $accountOpeningBalances = [];
                foreach ($cashSubCategory->accounts as $account) {
                    $openingEntries = $account->accountEntries()
                        ->whereHas('journalEntry', function ($query) {
                            $query->where('status', 'published')
                                ->where('company_id', auth()->user()->current_company_id);
                        })
                        ->whereDate('date', '<', $startDate)
                        ->get();

                    $accountOpeningBalances[$account->id] = [
                        'name' => $account->name,
                        'balance' => $openingEntries->sum('debit_amount') - $openingEntries->sum('credit_amount')
                    ];
                    $reportData['totals']['opening_balance'] += $accountOpeningBalances[$account->id]['balance'];
                }

                // Process each month in the period
                foreach ($periods as $period) {
                    $monthStart = Carbon::parse($period['start_date']);
                    $monthEnd = Carbon::parse($period['end_date']);

                    $monthData = [
                        'name' => $period['name'],
                        'start_date' => $period['start_date'],
                        'end_date' => $period['end_date'],
                        'accounts' => [],
                        'totals' => [
                            'opening_balance' => 0,
                            'inflows' => 0,
                            'outflows' => 0,
                            'net_change' => 0,
                            'closing_balance' => 0,
                        ]
                    ];

                    // Process each account for this month
                    foreach ($cashSubCategory->accounts as $account) {
                        $accountId = $account->id;

                        // Get entries for this account in this month
                        $entries = $account->accountEntries()
                            ->whereHas('journalEntry', function ($query) {
                                $query->where('status', 'published')
                                    ->where('company_id', auth()->user()->current_company_id);
                            })
                            ->whereBetween('date', [$monthStart, $monthEnd])
                            ->get();

                        $debit = $entries->sum('debit_amount');
                        $credit = $entries->sum('credit_amount');

                        // Calculate opening balance (for first month it's the initial opening balance)
                        $openingBalance = $monthData['name'] == $periods[0]['name']
                            ? $accountOpeningBalances[$accountId]['balance']
                            : ($monthData['accounts'][$accountId]['closing_balance'] ?? 0);

                        $netChange = $credit - $debit;
                        $closingBalance = $openingBalance + $netChange;

                        $accountData = [
                            'id' => $accountId,
                            'name' => $account->name,
                            'opening_balance' => $openingBalance,
                            'inflows' => $credit,
                            'outflows' => $debit,
                            'net_change' => $netChange,
                            'closing_balance' => $closingBalance,
                        ];

                        $monthData['accounts'][] = $accountData;

                        // Update month totals
                        $monthData['totals']['opening_balance'] += $openingBalance;
                        $monthData['totals']['inflows'] += $credit;
                        $monthData['totals']['outflows'] += $debit;
                        $monthData['totals']['net_change'] += $netChange;
                        $monthData['totals']['closing_balance'] += $closingBalance;

                        // Update report totals
                        if ($monthData['name'] == $periods[0]['name']) {
                            $reportData['totals']['opening_balance'] = $monthData['totals']['opening_balance'];
                        }
                        $reportData['totals']['total_inflows'] += $credit;
                        $reportData['totals']['total_outflows'] += $debit;
                    }

                    $reportData['totals']['net_change'] =
                        $reportData['totals']['total_inflows'] - $reportData['totals']['total_outflows'];
                    $reportData['totals']['closing_balance'] =
                        $reportData['totals']['opening_balance'] + $reportData['totals']['net_change'];

                    $reportData['months'][] = $monthData;
                }
            }

            if ($export) {
                $fileName = 'cash_summary_' . str_replace(' ', '_', strtolower($periodName)) . '_' . now()->format('Ymd_His') . '.xlsx';
                return Excel::download(
                    new CashSummaryReportExport($reportData),
                    $fileName
                );
            }

            return $reportData;
        } catch (\Throwable $th) {
            return [
                'error' => true,
                'message' => $th->getMessage(),
                'trace' => $th->getTrace()
            ];
        }
    }

    public function profitAndLossGroupbyCategory($request)
    {
        try {
            $export = $request->export ?? false;
            $rangeType = $request->range_type ?? 'monthly';
            $year = $request->year ?? now()->year;
            $month = $request->month ?? null;
            $quarter = $request->quarter ?? null;
            $customStart = $request->custom_start ?? null;
            $customEnd = $request->custom_end ?? null;

            // Determine date range based on selected type
            list($startDate, $endDate, $periodName) = $this->determineDateRange($rangeType, $year, $month, $quarter, $customStart, $customEnd);

            // Previous year dates for comparison
            $pYStartDate = $startDate->copy()->subYear();
            $pYEndDate = $endDate->copy()->subYear();

            // Get all relevant accounts
            $chartOfAccounts = FinanceChartOfAccount::whereHas('subCategory', function ($query) {
                $query->whereIn('slug', [
                    'sales',
                    'cost-of-sales',
                    'other-income',
                    'occupancy-expenses',
                    'selling-promotional-expense',
                    'personnel-expenses',
                    'administrative-expenses',
                    'finance-cost',
                    'income-tax-expense'
                ]);
            })
                ->orWhere('slug', 'vat-payable')
                ->with(['accountCategory.accountType', 'subCategory', 'accountEntries' => function ($query) use ($startDate, $endDate, $pYStartDate, $pYEndDate) {
                    $query->whereHas('journalEntry', function ($query) {
                        $query->where('status', 'published')
                            ->where('company_id',  auth()->user()->current_company_id);
                    })
                        ->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate])
                        ->orWhereBetween(DB::raw('DATE(date)'), [$pYStartDate, $pYEndDate]);
                }])
                ->get();

            // Initialize all totals
            $totals = [
                'current' => [
                    'revenue' => ['debit' => 0, 'credit' => 0],
                    'vat' => ['debit' => 0, 'credit' => 0],
                    'cogs' => ['debit' => 0, 'credit' => 0],
                    'other_income' => ['debit' => 0, 'credit' => 0],
                    'occupancy' => ['debit' => 0, 'credit' => 0],
                    'selling' => ['debit' => 0, 'credit' => 0],
                    'personnel' => ['debit' => 0, 'credit' => 0],
                    'admin' => ['debit' => 0, 'credit' => 0],
                    'finance' => ['debit' => 0, 'credit' => 0],
                    'tax' => ['debit' => 0, 'credit' => 0]
                ],
                'previous' => [
                    'revenue' => ['debit' => 0, 'credit' => 0],
                    'vat' => ['debit' => 0, 'credit' => 0],
                    'cogs' => ['debit' => 0, 'credit' => 0],
                    'other_income' => ['debit' => 0, 'credit' => 0],
                    'occupancy' => ['debit' => 0, 'credit' => 0],
                    'selling' => ['debit' => 0, 'credit' => 0],
                    'personnel' => ['debit' => 0, 'credit' => 0],
                    'admin' => ['debit' => 0, 'credit' => 0],
                    'finance' => ['debit' => 0, 'credit' => 0],
                    'tax' => ['debit' => 0, 'credit' => 0]
                ]
            ];

            // Process all account entries
            foreach ($chartOfAccounts as $account) {
                foreach ($account->accountEntries as $entry) {
                    $entryDate = Carbon::parse($entry->date);
                    $isCurrentYear = $entryDate->between($startDate, $endDate);
                    $isPreviousYear = $entryDate->between($pYStartDate, $pYEndDate);

                    if ($account->subCategory->slug == 'sales') {
                        $this->addToTotals($totals, $isCurrentYear, $isPreviousYear, 'revenue', $entry);
                    } elseif ($account->slug == 'vat-payable') {
                        $this->addToTotals($totals, $isCurrentYear, $isPreviousYear, 'vat', $entry);
                    } elseif ($account->subCategory->slug == 'cost-of-sales') {
                        $this->addToTotals($totals, $isCurrentYear, $isPreviousYear, 'cogs', $entry);
                    } elseif ($account->subCategory->slug == 'other-income') {
                        $this->addToTotals($totals, $isCurrentYear, $isPreviousYear, 'other_income', $entry);
                    } elseif ($account->subCategory->slug == 'occupancy-expenses') {
                        $this->addToTotals($totals, $isCurrentYear, $isPreviousYear, 'occupancy', $entry);
                    } elseif ($account->subCategory->slug == 'selling-promotional-expense') {
                        $this->addToTotals($totals, $isCurrentYear, $isPreviousYear, 'selling', $entry);
                    } elseif ($account->subCategory->slug == 'personnel-expenses') {
                        $this->addToTotals($totals, $isCurrentYear, $isPreviousYear, 'personnel', $entry);
                    } elseif ($account->subCategory->slug == 'administrative-expenses') {
                        $this->addToTotals($totals, $isCurrentYear, $isPreviousYear, 'admin', $entry);
                    } elseif ($account->subCategory->slug == 'finance-cost') {
                        $this->addToTotals($totals, $isCurrentYear, $isPreviousYear, 'finance', $entry);
                    } elseif ($account->subCategory->slug == 'income-tax-expense') {
                        $this->addToTotals($totals, $isCurrentYear, $isPreviousYear, 'tax', $entry);
                    }
                }
            }

            // Calculate all metrics
            $revenueCY = $this->calculateAmount('revenue', $totals['current']['revenue']);
            $revenuePY = $this->calculateAmount('revenue', $totals['previous']['revenue']);

            $vatCY = $this->calculateAmount('liability', $totals['current']['vat']);
            $vatPY = $this->calculateAmount('liability', $totals['previous']['vat']);

            $netSalesCY = $revenueCY; // Changed from $revenueCY - $vatCY
            $netSalesPY = $revenuePY; // Changed from $revenuePY - $vatPY

            $cogsCY = $this->calculateAmount('expense', $totals['current']['cogs']);
            $cogsPY = $this->calculateAmount('expense', $totals['previous']['cogs']);

            $grossProfitCY = $netSalesCY - $cogsCY;
            $grossProfitPY = $netSalesPY - $cogsPY;

            $otherIncomeCY = $this->calculateAmount('revenue', $totals['current']['other_income']);
            $otherIncomePY = $this->calculateAmount('revenue', $totals['previous']['other_income']);

            $occupancyCY = $this->calculateAmount('expense', $totals['current']['occupancy']);
            $occupancyPY = $this->calculateAmount('expense', $totals['previous']['occupancy']);

            $sellingCY = $this->calculateAmount('expense', $totals['current']['selling']);
            $sellingPY = $this->calculateAmount('expense', $totals['previous']['selling']);

            $personnelCY = $this->calculateAmount('expense', $totals['current']['personnel']);
            $personnelPY = $this->calculateAmount('expense', $totals['previous']['personnel']);

            $adminCY = $this->calculateAmount('expense', $totals['current']['admin']);
            $adminPY = $this->calculateAmount('expense', $totals['previous']['admin']);

            $operatingProfitCY = $grossProfitCY - ($occupancyCY + $sellingCY + $personnelCY + $adminCY);
            $operatingProfitPY = $grossProfitPY - ($occupancyPY + $sellingPY + $personnelPY + $adminPY);

            $financeCY = $this->calculateAmount('expense', $totals['current']['finance']);
            $financePY = $this->calculateAmount('expense', $totals['previous']['finance']);

            $profitBeforeTaxCY = $operatingProfitCY - $financeCY;
            $profitBeforeTaxPY = $operatingProfitPY - $financePY;

            $taxCY = $this->calculateAmount('expense', $totals['current']['tax']);
            $taxPY = $this->calculateAmount('expense', $totals['previous']['tax']);

            $profitAfterTaxCY = $profitBeforeTaxCY - $taxCY;
            $profitAfterTaxPY = $profitBeforeTaxPY - $taxPY;

            $otherComprehensiveIncomeCY = 0.00;
            $otherComprehensiveIncomePY = 0.00;

            $totalComprehensiveIncomeCY = $profitAfterTaxCY + $otherComprehensiveIncomeCY;
            $totalComprehensiveIncomePY = $profitAfterTaxPY + $otherComprehensiveIncomePY;

            // Prepare report data
            $data = [
                [
                    'is_bold' => true,
                    'key' => "sales",
                    'name' => "Revenue",
                    'totalCY' => $netSalesCY,
                    'totalPY' => $netSalesPY,
                ],
                [
                    'is_bold' => true,
                    'key' => "cost-of-sales",
                    'name' => "Cost of Sales",
                    'totalCY' => $cogsCY,
                    'totalPY' => $cogsPY,
                ],
                [
                    'is_bold' => true,
                    'key' => "",
                    'name' => "Gross Profit",
                    'totalCY' => $grossProfitCY,
                    'totalPY' => $grossProfitPY,
                ],
                [
                    'is_bold' => false,
                    'key' => "other-income",
                    'name' => "Other Income",
                    'totalCY' => $otherIncomeCY,
                    'totalPY' => $otherIncomePY,
                ],
                [
                    'is_bold' => false,
                    'key' => "occupancy-expenses",
                    'name' => "Occupancy Expenses",
                    'totalCY' => $occupancyCY,
                    'totalPY' => $occupancyPY,
                ],
                [
                    'is_bold' => false,
                    'key' => "selling-promotional-expense",
                    'name' => "Selling & Promotional Expense",
                    'totalCY' => $sellingCY,
                    'totalPY' => $sellingPY,
                ],
                [
                    'is_bold' => false,
                    'key' => "personnel-expense",
                    'name' => "Personnel Expense",
                    'totalCY' => $personnelCY,
                    'totalPY' => $personnelPY,
                ],
                [
                    'is_bold' => false,
                    'key' => "administrative-expenses",
                    'name' => "Administrative Expense",
                    'totalCY' => $adminCY,
                    'totalPY' => $adminPY,
                ],
                [
                    'is_bold' => true,
                    'key' => "",
                    'name' => "Operating Profit",
                    'totalCY' => $operatingProfitCY,
                    'totalPY' => $operatingProfitPY,
                ],
                [
                    'is_bold' => false,
                    'key' => "finance-cost",
                    'name' => "Finance Cost",
                    'totalCY' => $financeCY,
                    'totalPY' => $financePY,
                ],
                [
                    'is_bold' => true,
                    'key' => "",
                    'name' => "Profit / (loss) before tax",
                    'totalCY' => $profitBeforeTaxCY,
                    'totalPY' => $profitBeforeTaxPY,
                ],
                [
                    'is_bold' => true,
                    'key' => "income-tax-expense",
                    'name' => "Income Tax Expense",
                    'totalCY' => $taxCY,
                    'totalPY' => $taxPY,
                ],
                [
                    'is_bold' => false,
                    'key' => "",
                    'name' => "Profit / (loss) after tax",
                    'totalCY' => $profitAfterTaxCY,
                    'totalPY' => $profitAfterTaxPY,
                ],
                [
                    'key' => "",
                    'name' => "Other Comprehensive Income",
                    'totalCY' => $otherComprehensiveIncomeCY,
                    'totalPY' => $otherComprehensiveIncomePY,
                ],
                [
                    'is_bold' => true,
                    'key' => "",
                    'name' => "Total Comprehensive Income",
                    'totalCY' => $totalComprehensiveIncomeCY,
                    'totalPY' => $totalComprehensiveIncomePY,
                ]
            ];

            $dateInfo = [
                'startDate' => $startDate->format('d/M/Y'),
                'endDate' => $endDate->format('d/M/Y'),
                'startDatePY' => $pYStartDate->format('d/M/Y'),
                'endDatePY' => $pYEndDate->format('d/M/Y'),
                'today' => Carbon::now()->format('d/M/Y'),
                'range_type' => $rangeType,
                'period_name' => $periodName,
                'drilldown_startDatePY' => $pYStartDate->format('Y-m-d'),
                'drilldown_endDatePY' => $pYEndDate->format('Y-m-d'),
            ];

            $finalData = [
                'data' => $data,
                'date_info' => $dateInfo,
            ];

            if ($export) {
                return Excel::download(
                    new ProfitAndLossReportExportCategory($finalData),
                    'profit_and_loss_' . $rangeType . '_' . $periodName . '.xlsx'
                );
            }

            return $finalData;
        } catch (\Throwable $th) {
            return $th;
        }
    }

    public function accountTransactionReport($request)
    {
        try {
            $export = $request->export ?? false;
            $rangeType = $request->range_type ?? 'monthly';
            $year = $request->year ?? now()->year;
            $month = $request->month ?? null;
            $quarter = $request->quarter ?? null;
            $customStart = $request->custom_start ?? null;
            $customEnd = $request->custom_end ?? null;
            $accountIds = $request->account_ids ?? [];

            // Determine date range
            list($startDate, $endDate, $periodName) = $this->determineDateRange(
                $rangeType,
                $year,
                $month,
                $quarter,
                $customStart,
                $customEnd
            );

            // If no accounts selected, return empty response
            // if (empty($accountIds)) {
            //     return $this->emptyReportResponse($rangeType, $periodName, $startDate, $endDate, $accountIds);
            // }

            // Get account types for the selected accounts
            $accounts = FinanceChartOfAccount::whereIn('id', $accountIds)
                ->with(['accountCategory.accountType'])
                ->get()
                ->keyBy('id');

            // Calculate opening balances with proper account type handling
            $openingBalances = [];
            foreach ($accountIds as $accountId) {
                if (!isset($accounts[$accountId])) continue;

                $accountType = $accounts[$accountId]->accountCategory->accountType->slug ?? 'asset';

                $openingEntries = FinanceAccountEntry::where('account_id', $accountId)
                    ->whereHas('journalEntry', function ($query) {
                        $query->where('status', 'published')
                            ->where('company_id', auth()->user()->current_company_id);
                    })
                    ->whereDate('date', '<', $startDate)
                    ->get();

                $totalDebit = $openingEntries->sum('debit_amount');
                $totalCredit = $openingEntries->sum('credit_amount');

                list($entryType, $debit, $credit) = $this->determineAccountBalance(
                    $accountType,
                    $totalDebit,
                    $totalCredit
                );

                $openingBalances[$accountId] = ($entryType === 'debit') ? $debit : -$credit;
            }

            // Get transactions for selected accounts within date range
            $transactions = FinanceAccountEntry::whereIn('account_id', $accountIds)
                ->whereHas('journalEntry', function ($query) {
                    $query->where('status', 'published')
                        ->where('company_id', auth()->user()->current_company_id);
                })
                ->whereBetween('date', [$startDate, $endDate])
                ->with(['account:id,name,account_number', 'journalEntry:id,status'])
                ->orderBy('date')
                ->orderBy('created_at')
                ->get();

            // Process transactions with running balances
            $processedTransactions = [];
            $runningBalances = $openingBalances;
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($transactions as $transaction) {
                $accountId = $transaction->account_id;
                $accountType = $accounts[$accountId]->accountCategory->accountType->slug ?? 'asset';

                // Determine if this transaction increases or decreases the balance
                $balanceChange = $this->calculateBalanceChange(
                    $accountType,
                    $transaction->debit_amount,
                    $transaction->credit_amount
                );

                // Update running balance
                $runningBalances[$accountId] += $balanceChange;

                $processedTransactions[] = [
                    'date' => $transaction->date,
                    'account_id' => $accountId,
                    'account_name' => $transaction->account->name,
                    'account_number' => $transaction->account->account_number,
                    'account_type' => $accountType,
                    'description' => $transaction->description,
                    'reference' => $transaction->reference,
                    'debit' => $transaction->debit_amount,
                    'credit' => $transaction->credit_amount,
                    'balance' => $runningBalances[$accountId],
                    'gross' => $transaction->debit_amount + $transaction->credit_amount
                ];

                $totalDebit += $transaction->debit_amount;
                $totalCredit += $transaction->credit_amount;
            }

            // Calculate summary
            $netChange = $totalDebit - $totalCredit;
            $totalOpeningBalance = array_sum($openingBalances);
            $totalClosingBalance = $totalOpeningBalance + $netChange;

            $reportData = [
                'transactions' => $processedTransactions,
                'summary' => [
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'net_change' => $netChange,
                    'opening_balance' => $totalOpeningBalance,
                    'closing_balance' => $totalClosingBalance
                ],
                'period_info' => [
                    'range_type' => $rangeType,
                    'period_name' => $periodName,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'account_ids' => $accountIds
                ]
            ];

            return $reportData;
        } catch (\Throwable $th) {
            return $th;
        }
    }

    // public function accountTransactionReportOld($request)
    // {
    //     try {
    //         $export = $request->export ?? false;
    //         $rangeType = $request->range_type ?? 'monthly';
    //         $year = $request->year ?? now()->year;
    //         $month = $request->month ?? null;
    //         $quarter = $request->quarter ?? null;
    //         $customStart = $request->custom_start ?? null;
    //         $customEnd = $request->custom_end ?? null;
    //         $accountIds = $request->account_ids ?? [];

    //         // Determine date range based on selected type
    //         list($startDate, $endDate, $periodName) = $this->determineDateRange(
    //             $rangeType,
    //             $year,
    //             $month,
    //             $quarter,
    //             $customStart,
    //             $customEnd
    //         );

    //         // If no accounts selected, return empty response
    //         if (empty($accountIds)) {
    //             $reportData = [
    //                 'data' => [
    //                     'transactions' => [],
    //                     'summary' => [
    //                         'total_debit' => 0,
    //                         'total_credit' => 0,
    //                         'net_change' => 0,
    //                         'opening_balance' => 0,
    //                         'closing_balance' => 0
    //                     ],
    //                     'period_info' => [
    //                         'range_type' => $rangeType,
    //                         'period_name' => $periodName,
    //                         'start_date' => $startDate->format('Y-m-d'),
    //                         'end_date' => $endDate->format('Y-m-d'),
    //                         'account_ids' => $accountIds
    //                     ]
    //                 ]
    //             ];

    //             if ($export) {
    //                 $fileName = 'account_transactions' . '_' . Str::slug($reportData['period_info']['period_name']) . '_' . now()->format('Ymd_His');

    //                 if ($export === 'pdf') {
    //                     $pdf = Pdf::loadView('reports.account_transactions', $reportData);
    //                     return $pdf->download($fileName . '.pdf');
    //                 } elseif ($export === 'excel') {
    //                     return Excel::download(
    //                         new AccountTransactionsExport($reportData),
    //                         $fileName . '.xlsx'
    //                     );
    //                 }
    //             }
    //         }

    //         // Get all transactions for selected accounts within date range
    //         $transactions = FinanceAccountEntry::whereIn('account_id', $accountIds)
    //             ->whereHas('journalEntry', function ($query) {
    //                 $query->where('status', 'published')
    //                     ->where('company_id', auth()->user()->current_company_id);
    //             })
    //             ->whereBetween('date', [$startDate, $endDate])
    //             ->with(['account:id,name,account_number', 'journalEntry:id,status'])
    //             ->orderBy('date')
    //             ->orderBy('created_at')
    //             ->get();

    //         // Calculate opening balances (before start date)
    //         $openingBalances = [];
    //         foreach ($accountIds as $accountId) {
    //             $openingEntries = FinanceAccountEntry::where('account_id', $accountId)
    //                 ->whereHas('journalEntry', function ($query) {
    //                     $query->where('status', 'published')
    //                         ->where('company_id', auth()->user()->current_company_id);
    //                 })
    //                 ->whereDate('date', '<', $startDate)
    //                 ->get();
    //         }

    //         // Process transactions with running balances
    //         $processedTransactions = [];
    //         $runningBalances = $openingBalances;
    //         $totalDebit = 0;
    //         $totalCredit = 0;

    //         foreach ($transactions as $transaction) {
    //             $accountId = $transaction->account_id;

    //             // Calculate running balance
    //             $balanceChange = $transaction->debit_amount - $transaction->credit_amount;
    //             $runningBalances[$accountId] += $balanceChange;

    //             $processedTransactions[] = [
    //                 'date' => $transaction->date,
    //                 'account_id' => $accountId,
    //                 'account_name' => $transaction->account->name,
    //                 'account_number' => $transaction->account->account_number,
    //                 'description' => $transaction->description,
    //                 'reference' => $transaction->reference,
    //                 'debit' => $transaction->debit_amount,
    //                 'credit' => $transaction->credit_amount,
    //                 'balance' => $runningBalances[$accountId],
    //                 'gross' => $transaction->debit_amount + $transaction->credit_amount
    //             ];

    //             $totalDebit += $transaction->debit_amount;
    //             $totalCredit += $transaction->credit_amount;
    //         }

    //         // Calculate summary
    //         $netChange = $totalDebit - $totalCredit;
    //         $totalOpeningBalance = array_sum($openingBalances);
    //         $totalClosingBalance = $totalOpeningBalance + $netChange;

    //         $reportData = [
    //             'transactions' => $processedTransactions,
    //             'summary' => [
    //                 'total_debit' => $totalDebit,
    //                 'total_credit' => $totalCredit,
    //                 'net_change' => $netChange,
    //                 'opening_balance' => $totalOpeningBalance,
    //                 'closing_balance' => $totalClosingBalance
    //             ],
    //             'period_info' => [
    //                 'range_type' => $rangeType,
    //                 'period_name' => $periodName,
    //                 'start_date' => $startDate->format('Y-m-d'),
    //                 'end_date' => $endDate->format('Y-m-d'),
    //                 'account_ids' => $accountIds
    //             ]
    //         ];


    //         if ($export) {
    //             $fileName = 'account_transactions' . '_' . Str::slug($reportData['period_info']['period_name']) . '_' . now()->format('Ymd_His');
    //             return Excel::download(
    //                 new AccountTransactionsExport($reportData),
    //                 $fileName . '.xlsx'
    //             );
    //         }
    //         return $reportData;
    //     } catch (\Throwable $th) {
    //         return $th;
    //     }
    // }


    public function journalEntryReport($request)
    {
        try {
            $export = $request->export ?? false;
            $rangeType = $request->range_type ?? 'monthly';
            $year = $request->year ?? now()->year;
            $month = $request->month ?? null;
            $quarter = $request->quarter ?? null;
            $customStart = $request->custom_start ?? null;
            $customEnd = $request->custom_end ?? null;
            $accountIds = $request->account_ids ?? [];

            // Determine date range
            list($startDate, $endDate, $periodName) = $this->determineDateRange(
                $rangeType,
                $year,
                $month,
                $quarter,
                $customStart,
                $customEnd
            );

            // Base query for journal entries
            $query = FinanceJournalEntry::where('status', 'published')
                ->where('company_id', auth()->user()->current_company_id)
                // Remove this if not needed: ->where('info', 'JournalEntry')
                ->whereBetween('date', [$startDate, $endDate])
                ->with([
                    'accountEntries.account:id,name,account_number',
                    'editedBy:id,name' // Changed from editedBy to match usage
                ])
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc');

            // Apply account filtering if accounts are specified
            if (!empty($accountIds)) {
                $query->whereHas('accountEntries', function ($q) use ($accountIds) {
                    $q->whereIn('account_id', $accountIds);
                });
            }

            $journalEntries = $query->get();

            // Process journal entries
            $processedEntries = [];
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($journalEntries as $entry) {
                $entryDebit = 0;
                $entryCredit = 0;
                $entryAccounts = [];

                foreach ($entry->accountEntries as $accountEntry) {
                    $entryDebit += $accountEntry->debit_amount;
                    $entryCredit += $accountEntry->credit_amount;

                    $entryAccounts[] = [
                        'account_id' => $accountEntry->account_id,
                        'account_name' => $accountEntry->account->name,
                        'account_number' => $accountEntry->account->account_number, // Fixed typo
                        'debit' => $accountEntry->debit_amount,
                        'credit' => $accountEntry->credit_amount,
                        'reference' => $accountEntry->reference,
                        'description' => $accountEntry->description,
                    ];
                }

                // If filtering by accounts, check if this entry has at least one requested account
                // if (!empty($accountIds)) {
                //     $entryAccountIds = collect($entry->accountEntries)->pluck('account_id')->toArray();
                //     if (count(array_intersect($accountIds, $entryAccountIds)) === 0) {
                //         continue; // Skip if no matching accounts
                //     }
                // }

                // If filtering by accounts, check if this entry has at least all requested account
                if (!empty($accountIds)) {
                    $entryAccountIds = collect($entry->accountEntries)->pluck('account_id')->toArray();

                    // Only include if ALL selected account IDs exist in this entry
                    if (!empty(array_diff($accountIds, $entryAccountIds))) {
                        continue; // Skip if not all accountIds are found
                    }
                }

                $processedEntries[] = [
                    'id' => $entry->id,
                    'date' => $entry->date,
                    'created_by' => $entry->createdBy->name ?? 'System',
                    // 'total_debit' => $entryDebit,
                    // 'total_credit' => $entryCredit,
                    'accounts' => $entryAccounts
                ];

                $totalDebit += $entryDebit;
                $totalCredit += $entryCredit;
            }

            $reportData = [
                'journal_entries' => $processedEntries,
                'summary' => [
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'count' => count($processedEntries)
                ],
                'period_info' => [
                    'range_type' => $rangeType,
                    'period_name' => $periodName,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'account_ids' => $accountIds
                ]
            ];

            if ($export) {
                $fileName = 'journal_entry_' . Str::slug($periodName) . '_' . now()->format('Ymd_His') . '.xlsx';

                return Excel::download(
                    new JournalEntriesExport($reportData['data']), // Pass the correct data
                    $fileName
                );
            }

            return $reportData;
        } catch (\Throwable $th) {
            return [
                'error' => true,
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ];
        }
    }

    public function generalLedgerSummary($request)
    {
        try {
            $export = $request->export ?? false;
            $rangeType = $request->range_type ?? 'monthly';
            $year = $request->year ?? now()->year;
            $month = $request->month ?? null;
            $quarter = $request->quarter ?? null;
            $customStart = $request->custom_start ?? null;
            $customEnd = $request->custom_end ?? null;
            $accountIds = $request->account_ids ?? [];

            // Determine date range
            list($startDate, $endDate, $periodName) = $this->determineDateRange(
                $rangeType,
                $year,
                $month,
                $quarter,
                $customStart,
                $customEnd
            );

            // Get all accounts with their types
            $accounts = FinanceChartOfAccount::when(!empty($accountIds), function ($query) use ($accountIds) {
                $query->whereIn('id', $accountIds);
            })
                ->with(['accountCategory.accountType'])
                ->get();

            // Initialize summary totals
            $summaryTotals = [
                'opening_balance' => 0,
                'total_debit' => 0,
                'total_credit' => 0,
                'net_movement' => 0,
                'closing_balance' => 0
            ];

            $accountDetails = [];

            foreach ($accounts as $account) {
                $accountType = $account->accountCategory->accountType->slug ?? 'asset';

                // Calculate opening balance (before start date)
                $openingEntries = FinanceAccountEntry::where('account_id', $account->id)
                    ->whereHas('journalEntry', function ($query) {
                        $query->where('status', 'published')
                            ->where('company_id', auth()->user()->current_company_id);
                    })
                    ->whereDate('date', '<', $startDate)
                    ->get();

                $openingDebit = $openingEntries->sum('debit_amount');
                $openingCredit = $openingEntries->sum('credit_amount');

                list($entryType, $openingBalance) = $this->calculateAccountBalance(
                    $accountType,
                    $openingDebit,
                    $openingCredit
                );

                // Get period transactions
                $periodEntries = FinanceAccountEntry::where('account_id', $account->id)
                    ->whereHas('journalEntry', function ($query) {
                        $query->where('status', 'published')
                            ->where('company_id', auth()->user()->current_company_id);
                    })
                    ->whereBetween('date', [$startDate, $endDate])
                    ->get();

                $periodDebit = $periodEntries->sum('debit_amount');
                $periodCredit = $periodEntries->sum('credit_amount');

                // Calculate net movement based on account type
                $netMovement = $this->calculateNetMovement(
                    $accountType,
                    $periodDebit,
                    $periodCredit
                );

                // Calculate closing balance
                $closingBalance = $openingBalance + $netMovement;

                // Add to account details
                $accountDetails[] = [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'account_number' => $account->account_number,
                    'account_type' => $accountType,
                    'opening_balance' => $openingBalance,
                    'total_debit' => $periodDebit,
                    'total_credit' => $periodCredit,
                    'net_movement' => $netMovement,
                    'closing_balance' => $closingBalance
                ];

                // Update summary totals
                $summaryTotals['opening_balance'] += $openingBalance;
                $summaryTotals['total_debit'] += $periodDebit;
                $summaryTotals['total_credit'] += $periodCredit;
                $summaryTotals['net_movement'] += $netMovement;
                $summaryTotals['closing_balance'] += $closingBalance;
            }

            $reportData = [
                'accounts' => $accountDetails,
                'summary' => $summaryTotals,
                'period_info' => [
                    'range_type' => $rangeType,
                    'period_name' => $periodName,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'account_ids' => $accountIds
                ]
            ];

            if ($export) {
                $fileName = 'general_ledger_summary_' . Str::slug($periodName) . '_' . now()->format('Ymd_His');

                if ($export === 'pdf') {
                    $pdf = Pdf::loadView('reports.general_ledger_summary', $reportData);
                    return $pdf->download($fileName . '.pdf');
                } else {
                    return Excel::download(
                        new GeneralLedgerSummaryExport($reportData['data']),
                        $fileName . '.xlsx'
                    );
                }
            }

            return $reportData;
        } catch (\Throwable $th) {
            return [
                'error' => true,
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ];
        }
    }

    public function trialBalanceReport($request)
    {
        try {
            $export = $request->export ?? false;
            $rangeType = $request->range_type ?? 'monthly';
            $year = $request->year ?? now()->year;
            $month = $request->month ?? null;
            $quarter = $request->quarter ?? null;
            $customStart = $request->custom_start ?? null;
            $customEnd = $request->custom_end ?? null;
            $accountIds = $request->account_ids ?? [];
            $compareWithLastYear = $request->compare_with_last_year ?? false;

            // Determine date range for current year
            list($startDate, $endDate, $periodName) = $this->determineDateRange(
                $rangeType,
                $year,
                $month,
                $quarter,
                $customStart,
                $customEnd
            );

            // Determine date range for previous year if comparison is requested
            $lastYearData = null;
            if ($compareWithLastYear) {
                $lastYear = $year - 1;
                list($lastYearStartDate, $lastYearEndDate, $lastYearPeriodName) = $this->determineDateRange(
                    $rangeType,
                    $lastYear,
                    $month,
                    $quarter,
                    $customStart,
                    $customEnd
                );
            }

            // Get all accounts with their types
            $accounts = FinanceChartOfAccount::when(!empty($accountIds), function ($query) use ($accountIds) {
                $query->whereIn('id', $accountIds);
            })
                ->with(['accountCategory.accountType'])
                ->get();

            // Initialize summary totals
            $summaryTotals = [
                'current_year' => [
                    // 'opening_debit' => 0,
                    // 'opening_credit' => 0,
                    // 'movement_debit' => 0,
                    // 'movement_credit' => 0,
                    'closing_debit' => 0,
                    'closing_credit' => 0
                ],
                'last_year' => $compareWithLastYear ? [
                    // 'opening_debit' => 0,
                    // 'opening_credit' => 0,
                    // 'movement_debit' => 0,
                    // 'movement_credit' => 0,
                    'closing_debit' => 0,
                    'closing_credit' => 0
                ] : null
            ];

            $accountDetails = [];

            foreach ($accounts as $account) {
                $accountType = $account->accountCategory->accountType->slug ?? 'asset';

                // Process current year data
                $currentYearData = $this->processAccountPeriod(
                    $account->id,
                    $accountType,
                    $startDate,
                    $endDate
                );

                // Process last year data if comparison is requested
                $lastYearAccountData = null;
                if ($compareWithLastYear) {
                    $lastYearAccountData = $this->processAccountPeriod(
                        $account->id,
                        $accountType,
                        $lastYearStartDate,
                        $lastYearEndDate
                    );
                }

                // Add to account details
                $accountDetails[] = [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'account_number' => $account->account_number,
                    'account_type' => $accountType,
                    'current_year' => $currentYearData,
                    'last_year' => $lastYearAccountData
                ];

                // Update summary totals for current year
                $summaryTotals['current_year']['opening_debit'] += $currentYearData['opening_debit'];
                $summaryTotals['current_year']['opening_credit'] += $currentYearData['opening_credit'];
                $summaryTotals['current_year']['movement_debit'] += $currentYearData['movement_debit'];
                $summaryTotals['current_year']['movement_credit'] += $currentYearData['movement_credit'];
                $summaryTotals['current_year']['closing_debit'] += $currentYearData['closing_debit'];
                $summaryTotals['current_year']['closing_credit'] += $currentYearData['closing_credit'];

                // Update summary totals for last year if comparison is requested
                if ($compareWithLastYear && $lastYearAccountData) {
                    $summaryTotals['last_year']['opening_debit'] += $lastYearAccountData['opening_debit'];
                    $summaryTotals['last_year']['opening_credit'] += $lastYearAccountData['opening_credit'];
                    $summaryTotals['last_year']['movement_debit'] += $lastYearAccountData['movement_debit'];
                    $summaryTotals['last_year']['movement_credit'] += $lastYearAccountData['movement_credit'];
                    $summaryTotals['last_year']['closing_debit'] += $lastYearAccountData['closing_debit'];
                    $summaryTotals['last_year']['closing_credit'] += $lastYearAccountData['closing_credit'];
                }
            }

            $reportData = [
                'accounts' => $accountDetails,
                'summary' => $summaryTotals,
                'period_info' => [
                    'range_type' => $rangeType,
                    'current_year' => [
                        'period_name' => $periodName,
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d'),
                        'year' => $year
                    ],
                    'last_year' => $compareWithLastYear ? [
                        'period_name' => $lastYearPeriodName,
                        'start_date' => $lastYearStartDate->format('Y-m-d'),
                        'end_date' => $lastYearEndDate->format('Y-m-d'),
                        'year' => $lastYear
                    ] : null,
                    'account_ids' => $accountIds,
                    'compare_with_last_year' => $compareWithLastYear
                ]
            ];

            if ($export) {
                $fileName = 'trial_balance_' . Str::slug($periodName) .
                    ($compareWithLastYear ? '_vs_' . $lastYear : '') .
                    '_' . now()->format('Ymd_His');

                return Excel::download(
                    new TrialBalanceExport($reportData),
                    $fileName . '.xlsx'
                );
            }

            return $reportData;
        } catch (\Throwable $th) {
            return $th;
        }
    }

    private function processAccountPeriod($accountId, $accountType, $startDate, $endDate)
    {
        // Calculate opening balances (before start date)
        $openingEntries = FinanceAccountEntry::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($query) {
                $query->where('status', 'published')
                    ->where('company_id', auth()->user()->current_company_id);
            })
            ->whereDate('date', '<', $startDate)
            ->get();

        $openingDebit = $openingEntries->sum('debit_amount');
        $openingCredit = $openingEntries->sum('credit_amount');

        // Format opening balances for trial balance
        list($openingTbDebit, $openingTbCredit) = $this->formatTrialBalanceAmounts(
            $accountType,
            $openingDebit,
            $openingCredit
        );

        // Get period transactions
        $periodEntries = FinanceAccountEntry::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($query) {
                $query->where('status', 'published')
                    ->where('company_id', auth()->user()->current_company_id);
            })
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $periodDebit = $periodEntries->sum('debit_amount');
        $periodCredit = $periodEntries->sum('credit_amount');

        // Format movement amounts for trial balance
        list($movementTbDebit, $movementTbCredit) = $this->formatTrialBalanceAmounts(
            $accountType,
            $periodDebit,
            $periodCredit
        );

        // Calculate closing balances
        $closingDebit = $openingDebit + $periodDebit;
        $closingCredit = $openingCredit + $periodCredit;

        // Format closing balances for trial balance
        list($closingTbDebit, $closingTbCredit) = $this->formatTrialBalanceAmounts(
            $accountType,
            $closingDebit,
            $closingCredit
        );

        return [
            'opening_debit' => $openingTbDebit,
            'opening_credit' => $openingTbCredit,
            'movement_debit' => $movementTbDebit,
            'movement_credit' => $movementTbCredit,
            'closing_debit' => $closingTbDebit,
            'closing_credit' => $closingTbCredit
        ];
    }



    // public function trialBalanceReport($request)
    // {
    //     try {
    //         $export = $request->export ?? false;
    //         $rangeType = $request->range_type ?? 'monthly';
    //         $year = $request->year ?? now()->year;
    //         $month = $request->month ?? null;
    //         $quarter = $request->quarter ?? null;
    //         $customStart = $request->custom_start ?? null;
    //         $customEnd = $request->custom_end ?? null;
    //         $accountIds = $request->account_ids ?? [];

    //         // Determine date range
    //         list($startDate, $endDate, $periodName) = $this->determineDateRange(
    //             $rangeType,
    //             $year,
    //             $month,
    //             $quarter,
    //             $customStart,
    //             $customEnd
    //         );

    //         // Get all accounts with their types
    //         $accounts = FinanceChartOfAccount::when(!empty($accountIds), function ($query) use ($accountIds) {
    //             $query->whereIn('id', $accountIds);
    //         })
    //             ->with(['accountCategory.accountType'])
    //             ->get();

    //         // Initialize summary totals
    //         $summaryTotals = [
    //             'opening_debit' => 0,
    //             'opening_credit' => 0,
    //             'movement_debit' => 0,
    //             'movement_credit' => 0,
    //             'closing_debit' => 0,
    //             'closing_credit' => 0
    //         ];

    //         $accountDetails = [];

    //         foreach ($accounts as $account) {
    //             $accountType = $account->accountCategory->accountType->slug ?? 'asset';

    //             // Calculate opening balances (before start date)
    //             $openingEntries = FinanceAccountEntry::where('account_id', $account->id)
    //                 ->whereHas('journalEntry', function ($query) {
    //                     $query->where('status', 'published')
    //                         ->where('company_id', auth()->user()->current_company_id);
    //                 })
    //                 ->whereDate('date', '<', $startDate)
    //                 ->get();

    //             $openingDebit = $openingEntries->sum('debit_amount');
    //             $openingCredit = $openingEntries->sum('credit_amount');

    //             // Format opening balances for trial balance
    //             list($openingTbDebit, $openingTbCredit) = $this->formatTrialBalanceAmounts(
    //                 $accountType,
    //                 $openingDebit,
    //                 $openingCredit
    //             );

    //             // Get period transactions
    //             $periodEntries = FinanceAccountEntry::where('account_id', $account->id)
    //                 ->whereHas('journalEntry', function ($query) {
    //                     $query->where('status', 'published')
    //                         ->where('company_id', auth()->user()->current_company_id);
    //                 })
    //                 ->whereBetween('date', [$startDate, $endDate])
    //                 ->get();

    //             $periodDebit = $periodEntries->sum('debit_amount');
    //             $periodCredit = $periodEntries->sum('credit_amount');

    //             // Format movement amounts for trial balance
    //             list($movementTbDebit, $movementTbCredit) = $this->formatTrialBalanceAmounts(
    //                 $accountType,
    //                 $periodDebit,
    //                 $periodCredit
    //             );

    //             // Calculate closing balances
    //             $closingDebit = $openingDebit + $periodDebit;
    //             $closingCredit = $openingCredit + $periodCredit;

    //             // Format closing balances for trial balance
    //             list($closingTbDebit, $closingTbCredit) = $this->formatTrialBalanceAmounts(
    //                 $accountType,
    //                 $closingDebit,
    //                 $closingCredit
    //             );

    //             // Add to account details
    //             $accountDetails[] = [
    //                 'account_id' => $account->id,
    //                 'account_name' => $account->name,
    //                 'account_number' => $account->account_number,
    //                 'account_type' => $accountType,
    //                 'opening_debit' => $openingTbDebit,
    //                 'opening_credit' => $openingTbCredit,
    //                 'movement_debit' => $movementTbDebit,
    //                 'movement_credit' => $movementTbCredit,
    //                 'closing_debit' => $closingTbDebit,
    //                 'closing_credit' => $closingTbCredit
    //             ];

    //             // Update summary totals
    //             $summaryTotals['opening_debit'] += $openingTbDebit;
    //             $summaryTotals['opening_credit'] += $openingTbCredit;
    //             $summaryTotals['movement_debit'] += $movementTbDebit;
    //             $summaryTotals['movement_credit'] += $movementTbCredit;
    //             $summaryTotals['closing_debit'] += $closingTbDebit;
    //             $summaryTotals['closing_credit'] += $closingTbCredit;
    //         }

    //         $reportData = [
    //             'accounts' => $accountDetails,
    //             'summary' => $summaryTotals,
    //             'period_info' => [
    //                 'range_type' => $rangeType,
    //                 'period_name' => $periodName,
    //                 'start_date' => $startDate->format('Y-m-d'),
    //                 'end_date' => $endDate->format('Y-m-d'),
    //                 'account_ids' => $accountIds
    //             ]
    //         ];

    //         if ($export) {
    //             $fileName = 'trial_balance_' . Str::slug($periodName) . '_' . now()->format('Ymd_His');

    //             return Excel::download(
    //                 new TrialBalanceExport($reportData['data']),
    //                 $fileName . '.xlsx'
    //             );
    //         }

    //         return $reportData;
    //     } catch (\Throwable $th) {
    //         return $th;
    //     }
    // }

    private function formatTrialBalanceAmounts($accountType, $debitAmount, $creditAmount)
    {
        $typeMapping = [
            "expense" => "debit",
            "asset" => "debit",
            "liability" => "credit",
            "equity" => "credit",
            "revenue" => "credit",
            "income" => "credit",
        ];

        $entryType = $typeMapping[strtolower($accountType)] ?? "debit";

        if ($entryType === "debit") {
            $balance = $debitAmount - $creditAmount;
            return $balance >= 0 ? [$balance, 0] : [0, abs($balance)];
        } else {
            $balance = $creditAmount - $debitAmount;
            return $balance >= 0 ? [0, $balance] : [abs($balance), 0];
        }
    }

    // Keep the existing determineDateRange method from previous implementations


    private function calculateAccountBalance($accountType, $debitAmount, $creditAmount)
    {
        $typeMapping = [
            "expense" => "debit",
            "asset" => "debit",
            "liability" => "credit",
            "equity" => "credit",
            "revenue" => "credit",
            "income" => "credit",
        ];

        $entryType = $typeMapping[strtolower($accountType)] ?? "debit";

        if ($entryType === "debit") {
            $balance = $debitAmount - $creditAmount;
        } else {
            $balance = $creditAmount - $debitAmount;
        }

        return [$entryType, $balance];
    }

    private function calculateNetMovement($accountType, $debitAmount, $creditAmount)
    {
        $typeMapping = [
            "expense" => "debit",
            "asset" => "debit",
            "liability" => "credit",
            "equity" => "credit",
            "revenue" => "credit",
            "income" => "credit",
        ];

        $entryType = $typeMapping[strtolower($accountType)] ?? "debit";

        if ($entryType === "debit") {
            return $debitAmount - $creditAmount;
        } else {
            return $creditAmount - $debitAmount;
        }
    }

    // Keep the existing determineDateRange method from previous implementations


    private function determineAccountBalance($accountType, $totalDebit, $totalCredit)
    {
        $typeMapping = [
            "expense" => "debit",
            "asset" => "debit",
            "liability" => "credit",
            "equity" => "credit",
            "revenue" => "credit",
            "income" => "credit",
        ];

        $entryType = $typeMapping[strtolower($accountType)] ?? "debit";

        if ($entryType === "debit") {
            if ($totalDebit > $totalCredit) {
                $totalDebit -= $totalCredit;
                $totalCredit = 0.00;
            } elseif ($totalCredit > $totalDebit) {
                $totalCredit -= $totalDebit;
                $totalDebit = 0.00;
            }
        } else {
            if ($totalCredit > $totalDebit) {
                $totalCredit -= $totalDebit;
                $totalDebit = 0.00;
            } elseif ($totalDebit > $totalCredit) {
                $totalDebit -= $totalCredit;
                $totalCredit = 0.00;
            }
        }

        return [$entryType, $totalDebit, $totalCredit];
    }

    private function calculateBalanceChange($accountType, $debitAmount, $creditAmount)
    {
        $typeMapping = [
            "expense" => "debit",
            "asset" => "debit",
            "liability" => "credit",
            "equity" => "credit",
            "revenue" => "credit",
            "income" => "credit",
        ];

        $entryType = $typeMapping[strtolower($accountType)] ?? "debit";

        if ($entryType === "debit") {
            return $debitAmount - $creditAmount;
        } else {
            return $creditAmount - $debitAmount;
        }
    }

    private function emptyReportResponse($rangeType, $periodName, $startDate, $endDate, $accountIds)
    {
        return [
            'data' => [
                'transactions' => [],
                'summary' => [
                    'total_debit' => 0,
                    'total_credit' => 0,
                    'net_change' => 0,
                    'opening_balance' => 0,
                    'closing_balance' => 0
                ],
                'period_info' => [
                    'range_type' => $rangeType,
                    'period_name' => $periodName,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'account_ids' => $accountIds
                ]
            ]
        ];
    }



    private function determineDateRange($rangeType, $year, $month, $quarter, $customStart, $customEnd)
    {
        switch ($rangeType) {
            case 'yearly':
                $startDate = Carbon::create($year, 1, 1)->startOfYear();
                $endDate = Carbon::create($year, 12, 31)->endOfYear();
                $periodName = "Year {$year}";
                break;

            case 'quarterly':
                $quarter = $quarter ?? ceil(now()->month / 3);
                $startMonth = ($quarter - 1) * 3 + 1;
                $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
                $endDate = $startDate->copy()->addMonths(2)->endOfMonth();
                $periodName = "Q{$quarter} {$year}";
                break;

            case 'monthly':
                $month = $month ?? now()->month;
                $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                $endDate = $startDate->copy()->endOfMonth();
                $periodName = $startDate->format('F Y');
                break;

            case 'month_to_date':
                $startDate = Carbon::create($year, now()->month, 1)->startOfMonth();
                $endDate = now()->endOfDay();
                $periodName = "MTD " . $startDate->format('F Y');
                break;

            case 'quarter_to_date':
                $currentQuarter = ceil(now()->month / 3);
                $startMonth = ($currentQuarter - 1) * 3 + 1;
                $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
                $endDate = now()->endOfDay();
                $periodName = "QTD Q{$currentQuarter} {$year}";
                break;

            case 'year_to_date':
                $startDate = Carbon::create($year, 1, 1)->startOfYear();
                $endDate = now()->endOfDay();
                $periodName = "YTD {$year}";
                break;

            case 'life_to_date':
                $startDate = Carbon::create(2000, 1, 1)->startOfYear(); // Adjust as needed
                $endDate = now()->endOfDay();
                $periodName = 'Life to Date';
                break;

            case 'last_month':
                $startDate = now()->subMonth()->startOfMonth();
                $endDate = $startDate->copy()->endOfMonth();
                $periodName = "Last Month: " . $startDate->format('F Y');
                break;

            case 'last_quarter':
                $currentMonth = now()->month;
                $lastQuarter = ceil($currentMonth / 3) - 1;
                if ($lastQuarter < 1) {
                    $lastQuarter = 4;
                    $year--;
                }
                $startMonth = ($lastQuarter - 1) * 3 + 1;
                $startDate = Carbon::create($year, $startMonth, 1)->startOfMonth();
                $endDate = $startDate->copy()->addMonths(2)->endOfMonth();
                $periodName = "Last Quarter: Q{$lastQuarter} {$year}";
                break;

            case 'last_year':
                $startDate = Carbon::create($year - 1, 1, 1)->startOfYear();
                $endDate = $startDate->copy()->endOfYear();
                $periodName = "Last Year: " . ($year - 1);
                break;

            case 'custom_dates':
                $startDate = Carbon::parse($customStart)->startOfDay();
                $endDate = Carbon::parse($customEnd)->endOfDay();
                $periodName = $startDate->format('M d, Y') . ' to ' . $endDate->format('M d, Y');
                break;

            default:
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
                $periodName = now()->format('F Y');
        }

        return [$startDate, $endDate, $periodName];
    }

    private function addToTotals(&$totals, $isCurrentYear, $isPreviousYear, $type, $entry)
    {
        if ($isCurrentYear) {
            $totals['current'][$type]['debit'] += $entry->debit_amount;
            $totals['current'][$type]['credit'] += $entry->credit_amount;
        } elseif ($isPreviousYear) {
            $totals['previous'][$type]['debit'] += $entry->debit_amount;
            $totals['previous'][$type]['credit'] += $entry->credit_amount;
        }
    }

    private function calculateAmount($accountType, $amounts)
    {
        list($entryType, $debit, $credit) = $this->determineCreditOrDebitAccountType(
            $accountType,
            $amounts['debit'],
            $amounts['credit']
        );
        return max($debit, $credit);
    }

    private function determineCreditOrDebitAccountType($accountType, $closingDebit, $closingCredit)
    {
        $typeMapping = [
            "expense" => "debit",
            "asset" => "debit",
            "liability" => "credit",
            "equity" => "credit",
            "revenue" => "credit",
            "income" => "credit",
        ];

        $entryType = $typeMapping[$accountType] ?? "unknown";

        if ($entryType === "debit") {
            if ($closingDebit > $closingCredit) {
                $closingDebit -= $closingCredit;
                $closingCredit = 0.00;
            } elseif ($closingCredit > $closingDebit) {
                $closingCredit -= $closingDebit;
                $closingDebit = 0.00;
            }
        } else {
            if ($closingCredit > $closingDebit) {
                $closingCredit -= $closingDebit;
                $closingDebit = 0.00;
            } elseif ($closingDebit > $closingCredit) {
                $closingDebit -= $closingCredit;
                $closingCredit = 0.00;
            }
        }

        return [$entryType, $closingDebit, $closingCredit];
    }

    /**
     * Parse the primary date filter based on user selection
     */
    private function parseDateFilter($dateInput, $periodType)
    {
        $carbonDate = Carbon::parse($dateInput);

        switch ($periodType) {
            case 'specific_date':
                return [
                    'start_date' => $carbonDate->toDateString(),
                    'end_date' => $carbonDate->toDateString()
                ];

            case 'this_month':
                return [
                    'start_date' => $carbonDate->copy()->startOfMonth()->toDateString(),
                    'end_date' => $carbonDate->copy()->endOfMonth()->toDateString()
                ];

            case 'last_month':
                return [
                    'start_date' => $carbonDate->copy()->subMonth()->startOfMonth()->toDateString(),
                    'end_date' => $carbonDate->copy()->subMonth()->endOfMonth()->toDateString()
                ];

            case 'this_quarter':
                return [
                    'start_date' => $carbonDate->copy()->startOfQuarter()->toDateString(),
                    'end_date' => $carbonDate->copy()->endOfQuarter()->toDateString()
                ];

            case 'last_quarter':
                return [
                    'start_date' => $carbonDate->copy()->subQuarter()->startOfQuarter()->toDateString(),
                    'end_date' => $carbonDate->copy()->subQuarter()->endOfQuarter()->toDateString()
                ];

            case 'this_year_to_current_date':
                return [
                    'start_date' => $carbonDate->copy()->startOfYear()->toDateString(),
                    'end_date' => $carbonDate->toDateString()
                ];

            case 'this_quarter_to_current_date':
                return [
                    'start_date' => $carbonDate->copy()->startOfQuarter()->toDateString(),
                    'end_date' => $carbonDate->toDateString()
                ];

            case 'this_month_to_current_date':
                return [
                    'start_date' => $carbonDate->copy()->startOfMonth()->toDateString(),
                    'end_date' => $carbonDate->toDateString()
                ];

            case 'quarter_end':
                return [
                    'start_date' => $carbonDate->startOfQuarter()->toDateString(),
                    'end_date' => $carbonDate->endOfQuarter()->toDateString()
                ];

            case 'year_end':
                return [
                    'start_date' => $carbonDate->startOfYear()->toDateString(),
                    'end_date' => $carbonDate->endOfYear()->toDateString()
                ];

            case 'financial_year_end':
                // Assuming financial year ends March 31 (adjust as needed)
                $financialYearEnd = $carbonDate->month < 4
                    ? Carbon::create($carbonDate->year - 1, 3, 31)
                    : Carbon::create($carbonDate->year, 3, 31);

                return [
                    'start_date' => $financialYearEnd->copy()->subYear()->addDay()->toDateString(),
                    'end_date' => $financialYearEnd->toDateString()
                ];

            default:
                return [
                    'start_date' => $carbonDate->startOfYear()->toDateString(),
                    'end_date' => $carbonDate->endOfYear()->toDateString()
                ];
        }
    }


    /**
     * Parse the comparison date based on user selection
     */
    private function parseComparisonDate($baseDate, $compareWith, $periodType, $value)
    {
        $base = Carbon::parse($baseDate);
        $value = (int)$value;

        switch ($compareWith) {
            case 'years_ago':
                return [
                    'start_date' => $base->copy()->subYears($value)->startOfYear()->toDateString(),
                    'end_date' => $base->copy()->subYears($value)->endOfYear()->toDateString()
                ];

            case 'quarters_ago':
                return [
                    'start_date' => $base->copy()->subQuarters($value)->startOfQuarter()->toDateString(),
                    'end_date' => $base->copy()->subQuarters($value)->endOfQuarter()->toDateString()
                ];

            case 'months_ago':
                return [
                    'start_date' => $base->copy()->subMonths($value)->startOfMonth()->toDateString(),
                    'end_date' => $base->copy()->subMonths($value)->endOfMonth()->toDateString()
                ];

            case 'days_ago':
                return [
                    'start_date' => $base->copy()->subDays($value)->toDateString(),
                    'end_date' => $base->copy()->subDays($value)->toDateString()
                ];

            case 'previous_period':
                // Compare with same duration before the current period
                $currentStart = Carbon::parse($request->primary_start_date);
                $currentEnd = Carbon::parse($request->primary_end_date);
                $duration = $currentStart->diffInDays($currentEnd);

                return [
                    'start_date' => $currentStart->copy()->subDays($duration + 1)->toDateString(),
                    'end_date' => $currentStart->copy()->subDay()->toDateString()
                ];

            default:
                return [
                    'start_date' => $base->copy()->subYear()->startOfYear()->toDateString(),
                    'end_date' => $base->copy()->subYear()->endOfYear()->toDateString()
                ];
        }
    }

    /**
     * Process the balance sheet data structure
     */
    private function processBalanceSheetData($accountTypes, $startDate, $endDate, $pYStartDate, $pYEndDate, $currentRE, $previousRE)
    {
        $balanceSheet = [];

        foreach ($accountTypes as $accountType) {
            $accountTypeData = [
                'id' => $accountType->id,
                'name' => $accountType->name,
                'slug' => $accountType->slug,
                'totalCyBalance' => 0,
                'totalPyBalance' => 0,
                'movement' => 0,
                'accountCategories' => [],
            ];

            foreach ($accountType->accountCategories as $accountCategory) {
                $accountCategoryData = [
                    'id' => $accountCategory->id,
                    'name' => $accountCategory->name,
                    'slug' => $accountCategory->slug,
                    'totalCyBalance' => 0,
                    'totalPyBalance' => 0,
                    'movement' => 0,
                    'accountSubCategories' => [],
                ];

                foreach ($accountCategory->accountSubCategories as $accountSubCategory) {
                    $accountSubCategoryData = [
                        'id' => $accountSubCategory->id,
                        'name' => $accountSubCategory->name,
                        'slug' => $accountSubCategory->slug,
                        'cyBalance' => 0,
                        'pyBalance' => 0,
                        'movement' => 0,
                    ];

                    foreach ($accountSubCategory->accounts as $account) {
                        // Get current period balances
                        $cyDebitBalance = $account->accountEntries
                            ->whereBetween('date', [$startDate, $endDate])
                            ->sum('debit_amount');

                        $cyCreditBalance = $account->accountEntries
                            ->whereBetween('date', [$startDate, $endDate])
                            ->sum('credit_amount');

                        // Get previous period balances
                        $pyDebitBalance = $account->accountEntries
                            ->whereBetween('date', [$pYStartDate, $pYEndDate])
                            ->sum('debit_amount');

                        $pyCreditBalance = $account->accountEntries
                            ->whereBetween('date', [$pYStartDate, $pYEndDate])
                            ->sum('credit_amount');

                        // Determine account type treatment
                        [$entryType, $cyDebit, $cyCredit] = AccountEntriesCalculationHelper::determineCreditOrDebitAccountBalanceSheet(
                            $account,
                            $cyDebitBalance,
                            $cyCreditBalance
                        );

                        [$entryType, $pyDebit, $pyCredit] = AccountEntriesCalculationHelper::determineCreditOrDebitAccountBalanceSheet(
                            $account,
                            $pyDebitBalance,
                            $pyCreditBalance
                        );

                        $cyAccountBalance = $entryType == "debit" ? $cyDebit : ($entryType == "credit" ? $cyCredit : 0);
                        $pyAccountBalance = $entryType == "debit" ? $pyDebit : ($entryType == "credit" ? $pyCredit : 0);

                        $movement = $cyAccountBalance - $pyAccountBalance;

                        $accountSubCategoryData['movement'] += $movement;
                        $accountSubCategoryData['cyBalance'] += $cyAccountBalance;
                        $accountSubCategoryData['pyBalance'] += $pyAccountBalance;
                    }

                    $accountCategoryData['accountSubCategories'][] = $accountSubCategoryData;
                    $accountCategoryData['totalCyBalance'] += $accountSubCategoryData['cyBalance'];
                    $accountCategoryData['totalPyBalance'] += $accountSubCategoryData['pyBalance'];
                    $accountCategoryData['movement'] += $accountSubCategoryData['movement'];
                }

                $accountTypeData['accountCategories'][] = $accountCategoryData;
                $accountTypeData['totalCyBalance'] += $accountCategoryData['totalCyBalance'];
                $accountTypeData['totalPyBalance'] += $accountCategoryData['totalPyBalance'];
                $accountTypeData['movement'] += $accountCategoryData['movement'];
            }

            // Handle retained earnings for equity
            if ($accountType->slug === 'equity') {
                $totalAssetCy = $balanceSheet[0]['totalCyBalance'] ?? 0;
                $totalLiabilityCy = $balanceSheet[1]['totalCyBalance'] ?? 0;
                $totalEquityCy = $accountTypeData['totalCyBalance'];

                $totalAssetPy = $balanceSheet[0]['totalPyBalance'] ?? 0;
                $totalLiabilityPy = $balanceSheet[1]['totalPyBalance'] ?? 0;
                $totalEquityPy = $accountTypeData['totalPyBalance'];

                $retainedEarningsCy = $totalAssetCy - $totalLiabilityCy - $totalEquityCy;
                $retainedEarningsPy = $totalAssetPy - $totalLiabilityPy - $totalEquityPy;

                // Add calculated retained earnings to the appropriate subcategory
                foreach ($accountTypeData['accountCategories'] as &$category) {
                    foreach ($category['accountSubCategories'] as &$subCategory) {
                        if ($subCategory['slug'] === 'retained-earnings') {
                            $subCategory['cyBalance'] = $retainedEarningsCy + $currentRE;
                            $subCategory['pyBalance'] = $retainedEarningsPy + $previousRE;
                            $subCategory['movement'] = ($retainedEarningsCy + $currentRE) - ($retainedEarningsPy + $previousRE);

                            // Update category totals
                            $category['totalCyBalance'] += $retainedEarningsCy + $currentRE;
                            $category['totalPyBalance'] += $retainedEarningsPy + $previousRE;
                            $category['movement'] += $subCategory['movement'];
                        }
                    }
                }

                // Update type totals
                $accountTypeData['totalCyBalance'] += $retainedEarningsCy + $currentRE;
                $accountTypeData['totalPyBalance'] += $retainedEarningsPy + $previousRE;
                $accountTypeData['movement'] += ($retainedEarningsCy + $currentRE) - ($retainedEarningsPy + $previousRE);
            }

            $balanceSheet[] = $accountTypeData;
        }

        return $balanceSheet;
    }

    /**
     * Generate human-readable filter description
     */
    private function getFilterDescription($request)
    {
        $primaryDate = Carbon::parse($request->primary_date);

        switch ($request->primary_period_type) {
            case 'specific_date':
                return "As at " . $primaryDate->format('jS M Y');

            case 'quarter_end':
                return $primaryDate->format('F Y') . " Quarter";

            case 'year_end':
                return $primaryDate->format('Y') . " Year End";

            case 'financial_year_end':
                return $primaryDate->format('Y') . " Financial Year End";

            default:
                return $primaryDate->format('Y') . " Year End";
        }
    }

    /**
     * Generate human-readable comparison description
     */
    private function getComparisonDescription($request)
    {
        if ($request->compare_with === 'none') {
            return null;
        }

        $value = (int)$request->compare_value;
        $period = $this->getPeriodName($request->compare_period, $value);

        switch ($request->compare_with) {
            case 'years_ago':
                return "Compared with $value $period ago";

            case 'quarters_ago':
                return "Compared with $value $period ago";

            case 'months_ago':
                return "Compared with $value $period ago";

            case 'days_ago':
                return "Compared with $value $period ago";

            case 'previous_period':
                return "Compared with previous period";

            default:
                return "Compared with previous year";
        }
    }

    private function getPeriodName($period, $value)
    {
        $periods = [
            'years' => $value === 1 ? 'year' : 'years',
            'quarters' => $value === 1 ? 'quarter' : 'quarters',
            'months' => $value === 1 ? 'month' : 'months',
            'days' => $value === 1 ? 'day' : 'days',
        ];

        return $periods[$period] ?? 'period';
    }




    protected static function averageDayToGetPaid()
    {
        return 15; // Static number of days
    }

    protected static function valueOfUnpaidInvoices()
    {
        return 12500.75; // Static amount
    }

    protected static function averageDayToToPaySuppliers()
    {
        return 25; // Static number of days
    }

    protected static function valueOfUnpaidBills()
    {
        return 8750.50; // Static amount
    }
}
