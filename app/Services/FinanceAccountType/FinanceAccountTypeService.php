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
                    'code' => $account->code,
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
