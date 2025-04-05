<?php

namespace App\Services\FinanceAccountType;

use App\Exceptions\BadRequestException;
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
                            $query->where('status', 'published');
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
                                $query->where('status', 'published');
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
                                $query->where('status', 'published');
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


    // public function getExpensesReport($request)
    // {
    //     try {
    //         $export = $request->export;
    //         $currentDate = Carbon::now()->format('d/M/Y');

    //         $endDate = is_numeric($request->end_date) && strlen($request->end_date) === 4
    //             ? Carbon::create($request->end_date, 12, 31)->toDateString()
    //             : Carbon::parse($request->end_date)->toDateString();

    //         $startDate = Carbon::parse($endDate)->startOfYear()->toDateString();
    //         $pYEndDate = Carbon::parse($endDate)->subYear()->endOfYear()->toDateString();
    //         $pYStartDate = Carbon::parse($pYEndDate)->startOfYear()->toDateString();

    //         // Get expense account type with data for current and previous year
    //         $expenseAccountType = FinanceAccountType::where("slug", "expense")
    //             ->with([
    //                 'accountCategories.accountSubCategories.accounts' => function ($query) use ($startDate, $endDate, $pYStartDate, $pYEndDate) {
    //                     $query->with(['accountEntries' => function ($query) use ($startDate, $endDate, $pYStartDate, $pYEndDate) {
    //                         $query->whereHas('journalEntry', function ($query) {
    //                             $query->where('status', 'published');
    //                         })
    //                             ->where(function ($query) use ($startDate, $endDate, $pYStartDate, $pYEndDate) {
    //                                 $query->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate])
    //                                     ->orWhereBetween(DB::raw('DATE(date)'), [$pYStartDate, $pYEndDate]);
    //                             });
    //                     }]);
    //                 }
    //             ])
    //             ->first();

    //         $reportData = [
    //             'currentYear' => Carbon::parse($endDate)->year,
    //             'previousYear' => Carbon::parse($pYEndDate)->year,
    //             'categories' => [],
    //             'totalCurrent' => 0,
    //             'totalPrevious' => 0,
    //             'currentDate' => $currentDate,
    //         ];

    //         foreach ($expenseAccountType->accountCategories as $category) {
    //             $categoryData = [
    //                 'name' => $category->name,
    //                 'totalCurrent' => 0,
    //                 'totalPrevious' => 0,
    //                 'subcategories' => [],
    //             ];

    //             foreach ($category->accountSubCategories as $subCategory) {
    //                 $subCategoryData = [
    //                     'name' => $subCategory->name,
    //                     'totalCurrent' => 0,
    //                     'totalPrevious' => 0,
    //                     'accounts' => [],
    //                 ];

    //                 foreach ($subCategory->accounts as $account) {
    //                     // Current year amounts
    //                     $cyDebit = $account->accountEntries
    //                         ->whereBetween('date', [$startDate, $endDate])
    //                         ->sum('debit_amount');
    //                     $cyCredit = $account->accountEntries
    //                         ->whereBetween('date', [$startDate, $endDate])
    //                         ->sum('credit_amount');
    //                     $cyBalance = $cyDebit - $cyCredit; // Expense is debit - credit

    //                     // Previous year amounts
    //                     $pyDebit = $account->accountEntries
    //                         ->whereBetween('date', [$pYStartDate, $pYEndDate])
    //                         ->sum('debit_amount');
    //                     $pyCredit = $account->accountEntries
    //                         ->whereBetween('date', [$pYStartDate, $pYEndDate])
    //                         ->sum('credit_amount');
    //                     $pyBalance = $pyDebit - $pyCredit;

    //                     $accountData = [
    //                         'name' => $account->name,
    //                         'current' => $cyBalance,
    //                         'previous' => $pyBalance,
    //                     ];

    //                     $subCategoryData['accounts'][] = $accountData;
    //                     $subCategoryData['totalCurrent'] += $cyBalance;
    //                     $subCategoryData['totalPrevious'] += $pyBalance;
    //                 }

    //                 $categoryData['subcategories'][] = $subCategoryData;
    //                 $categoryData['totalCurrent'] += $subCategoryData['totalCurrent'];
    //                 $categoryData['totalPrevious'] += $subCategoryData['totalPrevious'];
    //             }

    //             $reportData['categories'][] = $categoryData;
    //             $reportData['totalCurrent'] += $categoryData['totalCurrent'];
    //             $reportData['totalPrevious'] += $categoryData['totalPrevious'];
    //         }

    //         if ($export) {
    //             $fileName = 'expenses_report' . now()->format('Ymd_His') . '.xlsx';

    //             return Excel::download(
    //                 new ExpensesReportExport($reportData),
    //                 $fileName
    //             );
    //         }

    //         return $reportData;
    //     } catch (\Throwable $th) {
    //         return $th;
    //     }
    // }

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
                                $query->where('status', 'published');
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
                            $query->where('status', 'published');
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
                        $query->where('status', 'published');
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
                            $query->where('status', 'published');
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
