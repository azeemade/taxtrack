<?php

namespace App\Http\Controllers\v1\Company\Report;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Services\FinanceAccountType\FinanceAccountTypeService;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class FinancialPerformanceController extends Controller
{
    protected FinanceAccountTypeService $financeAccountTypeService;

    public function __construct(FinanceAccountTypeService $financeAccountTypeService)
    {
        $this->financeAccountTypeService = $financeAccountTypeService;
    }

    public function getProfitAndLossReport(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->getProfitAndLossReport($request);

            if ($request->export) {
                return $records;
            }
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }


    // public function getProfitAndLossReport(Request $request)
    // {
    //     try {
    //         $export = $request->export; // pdf, excel
    //         $currentDate = Carbon::now()->format('d/M/Y');

    //         // Determine dates
    //         $endDate = is_numeric($request->end_date) && strlen($request->end_date) === 4
    //             ? Carbon::create($request->end_date, 12, 31)->toDateString()
    //             : Carbon::parse($request->end_date)->toDateString();

    //         $startDate = Carbon::parse($endDate)->startOfYear()->toDateString();
    //         $pYEndDate = Carbon::parse($endDate)->subYear()->endOfYear()->toDateString();
    //         $pYStartDate = Carbon::parse($pYEndDate)->startOfYear()->toDateString();

    //         // Get revenue and expense account types
    //         $accountTypes = FinanceAccountType::whereIn("slug", ["revenue", "expense"])
    //             ->with([
    //                 'accountCategories.accountSubCategories.accounts.accountEntries' => function ($query) use ($startDate, $endDate, $pYStartDate, $pYEndDate) {
    //                     $query->whereHas('journalEntry', function ($query) {
    //                         $query->where('is_published', 'true');
    //                     })
    //                         ->where(function ($query) use ($startDate, $endDate, $pYStartDate, $pYEndDate) {
    //                             $query->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate])
    //                                 ->orWhereBetween(DB::raw('DATE(date)'), [$pYStartDate, $pYEndDate]);
    //                         });
    //                 }
    //             ])
    //             ->get();

    //         $reportData = [
    //             'currentYear' => Carbon::parse($endDate)->year,
    //             'previousYear' => Carbon::parse($pYEndDate)->year,
    //             'currentPeriod' => ['start' => $startDate, 'end' => $endDate],
    //             'previousPeriod' => ['start' => $pYStartDate, 'end' => $pYEndDate],
    //             'categories' => [],
    //             'totalRevenueCurrent' => 0,
    //             'totalRevenuePrevious' => 0,
    //             'totalExpenseCurrent' => 0,
    //             'totalExpensePrevious' => 0,
    //             'netProfitCurrent' => 0,
    //             'netProfitPrevious' => 0,
    //         ];

    //         foreach ($accountTypes as $accountType) {
    //             $typeData = [
    //                 'name' => $accountType->name,
    //                 'slug' => $accountType->slug,
    //                 'totalCurrent' => 0,
    //                 'totalPrevious' => 0,
    //                 'categories' => [],
    //             ];

    //             foreach ($accountType->accountCategories as $category) {
    //                 $categoryData = [
    //                     'name' => $category->name,
    //                     'slug' => $category->slug,
    //                     'totalCurrent' => 0,
    //                     'totalPrevious' => 0,
    //                     'subcategories' => [],
    //                 ];

    //                 foreach ($category->accountSubCategories as $subCategory) {
    //                     $subCategoryData = [
    //                         'name' => $subCategory->name,
    //                         'slug' => $subCategory->slug,
    //                         'totalCurrent' => 0,
    //                         'totalPrevious' => 0,
    //                     ];

    //                     foreach ($subCategory->accounts as $account) {
    //                         // Current year amounts
    //                         $cyDebit = $account->accountEntries
    //                             ->whereBetween('date', [$startDate, $endDate])
    //                             ->sum('debit_amount');
    //                         $cyCredit = $account->accountEntries
    //                             ->whereBetween('date', [$startDate, $endDate])
    //                             ->sum('credit_amount');

    //                         // Previous year amounts
    //                         $pyDebit = $account->accountEntries
    //                             ->whereBetween('date', [$pYStartDate, $pYEndDate])
    //                             ->sum('debit_amount');
    //                         $pyCredit = $account->accountEntries
    //                             ->whereBetween('date', [$pYStartDate, $pYEndDate])
    //                             ->sum('credit_amount');

    //                         // Determine balance based on account type
    //                         if ($accountType->slug === 'revenue') {
    //                             $cyBalance = $cyCredit - $cyDebit;
    //                             $pyBalance = $pyCredit - $pyDebit;
    //                         } else { // expense
    //                             $cyBalance = $cyDebit - $cyCredit;
    //                             $pyBalance = $pyDebit - $pyCredit;
    //                         }

    //                         $subCategoryData['totalCurrent'] += $cyBalance;
    //                         $subCategoryData['totalPrevious'] += $pyBalance;
    //                     }

    //                     $categoryData['subcategories'][] = $subCategoryData;
    //                     $categoryData['totalCurrent'] += $subCategoryData['totalCurrent'];
    //                     $categoryData['totalPrevious'] += $subCategoryData['totalPrevious'];
    //                 }

    //                 $typeData['categories'][] = $categoryData;
    //                 $typeData['totalCurrent'] += $categoryData['totalCurrent'];
    //                 $typeData['totalPrevious'] += $categoryData['totalPrevious'];
    //             }

    //             if ($accountType->slug === 'revenue') {
    //                 $reportData['totalRevenueCurrent'] = $typeData['totalCurrent'];
    //                 $reportData['totalRevenuePrevious'] = $typeData['totalPrevious'];
    //             } else {
    //                 $reportData['totalExpenseCurrent'] = $typeData['totalCurrent'];
    //                 $reportData['totalExpensePrevious'] = $typeData['totalPrevious'];
    //             }

    //             $reportData['categories'][] = $typeData;
    //         }

    //         // Calculate net profit
    //         $reportData['netProfitCurrent'] = $reportData['totalRevenueCurrent'] - $reportData['totalExpenseCurrent'];
    //         $reportData['netProfitPrevious'] = $reportData['totalRevenuePrevious'] - $reportData['totalExpensePrevious'];

    //         // Handle export
    //         if ($export === "pdf") {
    //             $pdf = Pdf::loadView('reports.profit_and_loss', ['data' => $reportData]);
    //             return $pdf->download('profit_and_loss.pdf');
    //         } elseif ($export === "excel") {
    //             return Excel::download(new ProfitAndLossReportExport($reportData), 'profit_and_loss.xlsx');
    //         }

    //         return JsonResponser::send(false, 'Profit and Loss report generated successfully', $reportData, 200);
    //     } catch (\Throwable $th) {
    //         return JsonResponser::send(true, 'Internal server error!', $th->getMessage(), 500);
    //     }
    // }


    public function getIncomeReport(Request $request)
    {
        try {
            $export = $request->export;
            $currentDate = Carbon::now()->format('d/M/Y');

            $endDate = is_numeric($request->end_date) && strlen($request->end_date) === 4
                ? Carbon::create($request->end_date, 12, 31)->toDateString()
                : Carbon::parse($request->end_date)->toDateString();

            $startDate = Carbon::parse($endDate)->startOfYear()->toDateString();

            // Get revenue account type with monthly breakdown
            $revenueAccountType = FinanceAccountType::where("slug", "revenue")
                ->with([
                    'accountCategories.accountSubCategories.accounts' => function ($query) use ($startDate, $endDate) {
                        $query->with(['accountEntries' => function ($query) use ($startDate, $endDate) {
                            $query->whereHas('journalEntry', function ($query) {
                                $query->where('is_published', 'true');
                            })
                                ->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate]);
                        }]);
                    }
                ])
                ->first();

            // Prepare monthly data
            $months = [];
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
                        ];

                        foreach ($subCategory->accounts as $account) {
                            $debit = $account->accountEntries
                                ->whereBetween('date', [$monthStart, $monthEnd])
                                ->sum('debit_amount');
                            $credit = $account->accountEntries
                                ->whereBetween('date', [$monthStart, $monthEnd])
                                ->sum('credit_amount');

                            $balance = $credit - $debit; // Revenue is credit - debit
                            $subCategoryData['total'] += $balance;
                        }

                        $categoryData['subcategories'][] = $subCategoryData;
                        $categoryData['total'] += $subCategoryData['total'];
                    }

                    $monthData['categories'][] = $categoryData;
                    $monthData['total'] += $categoryData['total'];
                }

                $totalRevenue += $monthData['total'];
                $months[] = $monthData;
                $currentMonth->addMonth();
            }

            $reportData = [
                'year' => Carbon::parse($endDate)->year,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'months' => $months,
                'totalRevenue' => $totalRevenue,
                'currentDate' => $currentDate,
            ];

            if ($export === "pdf") {
                $pdf = Pdf::loadView('reports.income', ['data' => $reportData]);
                return $pdf->download('income_report.pdf');
            } elseif ($export === "excel") {
                return Excel::download(new IncomeReportExport($reportData), 'income_report.xlsx');
            }

            return JsonResponser::send(false, 'Income report generated successfully', $reportData, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error!', $th->getMessage(), 500);
        }
    }

    public function getExpensesReport(Request $request)
    {
        try {
            $export = $request->export;
            $currentDate = Carbon::now()->format('d/M/Y');

            $endDate = is_numeric($request->end_date) && strlen($request->end_date) === 4
                ? Carbon::create($request->end_date, 12, 31)->toDateString()
                : Carbon::parse($request->end_date)->toDateString();

            $startDate = Carbon::parse($endDate)->startOfYear()->toDateString();
            $pYEndDate = Carbon::parse($endDate)->subYear()->endOfYear()->toDateString();
            $pYStartDate = Carbon::parse($pYEndDate)->startOfYear()->toDateString();

            // Get expense account type with data for current and previous year
            $expenseAccountType = FinanceAccountType::where("slug", "expense")
                ->with([
                    'accountCategories.accountSubCategories.accounts' => function ($query) use ($startDate, $endDate, $pYStartDate, $pYEndDate) {
                        $query->with(['accountEntries' => function ($query) use ($startDate, $endDate, $pYStartDate, $pYEndDate) {
                            $query->whereHas('journalEntry', function ($query) {
                                $query->where('is_published', 'true');
                            })
                                ->where(function ($query) use ($startDate, $endDate, $pYStartDate, $pYEndDate) {
                                    $query->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate])
                                        ->orWhereBetween(DB::raw('DATE(date)'), [$pYStartDate, $pYEndDate]);
                                });
                        }]);
                    }
                ])
                ->first();

            $reportData = [
                'currentYear' => Carbon::parse($endDate)->year,
                'previousYear' => Carbon::parse($pYEndDate)->year,
                'categories' => [],
                'totalCurrent' => 0,
                'totalPrevious' => 0,
                'currentDate' => $currentDate,
            ];

            foreach ($expenseAccountType->accountCategories as $category) {
                $categoryData = [
                    'name' => $category->name,
                    'totalCurrent' => 0,
                    'totalPrevious' => 0,
                    'subcategories' => [],
                ];

                foreach ($category->accountSubCategories as $subCategory) {
                    $subCategoryData = [
                        'name' => $subCategory->name,
                        'totalCurrent' => 0,
                        'totalPrevious' => 0,
                        'accounts' => [],
                    ];

                    foreach ($subCategory->accounts as $account) {
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

                        $accountData = [
                            'name' => $account->name,
                            'current' => $cyBalance,
                            'previous' => $pyBalance,
                        ];

                        $subCategoryData['accounts'][] = $accountData;
                        $subCategoryData['totalCurrent'] += $cyBalance;
                        $subCategoryData['totalPrevious'] += $pyBalance;
                    }

                    $categoryData['subcategories'][] = $subCategoryData;
                    $categoryData['totalCurrent'] += $subCategoryData['totalCurrent'];
                    $categoryData['totalPrevious'] += $subCategoryData['totalPrevious'];
                }

                $reportData['categories'][] = $categoryData;
                $reportData['totalCurrent'] += $categoryData['totalCurrent'];
                $reportData['totalPrevious'] += $categoryData['totalPrevious'];
            }

            if ($export === "pdf") {
                $pdf = Pdf::loadView('reports.expenses', ['data' => $reportData]);
                return $pdf->download('expenses_report.pdf');
            } elseif ($export === "excel") {
                return Excel::download(new ExpensesReportExport($reportData), 'expenses_report.xlsx');
            }

            return JsonResponser::send(false, 'Expenses report generated successfully', $reportData, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error!', $th->getMessage(), 500);
        }
    }

    public function getNetProfitMarginReport(Request $request)
    {
        try {
            $export = $request->export;
            $currentDate = Carbon::now()->format('d/M/Y');

            $endDate = is_numeric($request->end_date) && strlen($request->end_date) === 4
                ? Carbon::create($request->end_date, 12, 31)->toDateString()
                : Carbon::parse($request->end_date)->toDateString();

            $startDate = Carbon::parse($endDate)->startOfYear()->toDateString();

            // Get revenue and expense account types for monthly breakdown
            $accountTypes = FinanceAccountType::whereIn("slug", ["revenue", "expense"])
                ->with([
                    'accountCategories.accountSubCategories.accounts' => function ($query) use ($startDate, $endDate) {
                        $query->with(['accountEntries' => function ($query) use ($startDate, $endDate) {
                            $query->whereHas('journalEntry', function ($query) {
                                $query->where('is_published', 'true');
                            })
                                ->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate]);
                        }]);
                    }
                ])
                ->get();

            // Prepare monthly data
            $months = [];
            $currentMonth = Carbon::parse($startDate);
            $yearTotalRevenue = 0;
            $yearTotalExpense = 0;

            while ($currentMonth->lte(Carbon::parse($endDate))) {
                $monthStart = $currentMonth->copy()->startOfMonth();
                $monthEnd = $currentMonth->copy()->endOfMonth();

                $monthRevenue = 0;
                $monthExpense = 0;

                foreach ($accountTypes as $accountType) {
                    foreach ($accountType->accountCategories as $category) {
                        foreach ($category->accountSubCategories as $subCategory) {
                            foreach ($subCategory->accounts as $account) {
                                $debit = $account->accountEntries
                                    ->whereBetween('date', [$monthStart, $monthEnd])
                                    ->sum('debit_amount');
                                $credit = $account->accountEntries
                                    ->whereBetween('date', [$monthStart, $monthEnd])
                                    ->sum('credit_amount');

                                if ($accountType->slug === 'revenue') {
                                    $balance = $credit - $debit;
                                    $monthRevenue += $balance;
                                } else {
                                    $balance = $debit - $credit;
                                    $monthExpense += $balance;
                                }
                            }
                        }
                    }
                }

                $monthNetProfit = $monthRevenue - $monthExpense;
                $margin = $monthRevenue != 0 ? ($monthNetProfit / $monthRevenue) * 100 : 0;

                $months[] = [
                    'name' => $currentMonth->format('F Y'),
                    'revenue' => $monthRevenue,
                    'expense' => $monthExpense,
                    'netProfit' => $monthNetProfit,
                    'margin' => round($margin, 2),
                ];

                $yearTotalRevenue += $monthRevenue;
                $yearTotalExpense += $monthExpense;
                $currentMonth->addMonth();
            }

            $yearNetProfit = $yearTotalRevenue - $yearTotalExpense;
            $yearMargin = $yearTotalRevenue != 0 ? ($yearNetProfit / $yearTotalRevenue) * 100 : 0;

            $reportData = [
                'year' => Carbon::parse($endDate)->year,
                'months' => $months,
                'yearTotalRevenue' => $yearTotalRevenue,
                'yearTotalExpense' => $yearTotalExpense,
                'yearNetProfit' => $yearNetProfit,
                'yearMargin' => round($yearMargin, 2),
                'currentDate' => $currentDate,
            ];

            if ($export === "pdf") {
                $pdf = Pdf::loadView('reports.net_profit_margin', ['data' => $reportData]);
                return $pdf->download('net_profit_margin.pdf');
            } elseif ($export === "excel") {
                return Excel::download(new NetProfitMarginReportExport($reportData), 'net_profit_margin.xlsx');
            }

            return JsonResponser::send(false, 'Net Profit Margin report generated successfully', $reportData, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error!', $th->getMessage(), 500);
        }
    }

    public function getCashBalancesReport(Request $request)
    {
        try {
            $export = $request->export;
            $currentDate = Carbon::now()->format('d/M/Y');

            $endDate = is_numeric($request->end_date) && strlen($request->end_date) === 4
                ? Carbon::create($request->end_date, 12, 31)->toDateString()
                : Carbon::parse($request->end_date)->toDateString();

            $startDate = Carbon::parse($endDate)->startOfYear()->toDateString();
            $pYEndDate = Carbon::parse($endDate)->subYear()->endOfYear()->toDateString();

            // Get cash accounts (assuming they are under asset type with a cash category)
            $cashAccounts = FinanceAccount::whereHas('accountSubCategory.accountCategory', function ($query) {
                $query->where('slug', 'cash-and-cash-equivalents')
                    ->whereHas('accountType', function ($query) {
                        $query->where('slug', 'asset');
                    });
            })
                ->with(['accountEntries' => function ($query) use ($startDate, $endDate, $pYEndDate) {
                    $query->whereHas('journalEntry', function ($query) {
                        $query->where('is_published', 'true');
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
                'currentDate' => $currentDate,
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

            if ($export === "pdf") {
                $pdf = Pdf::loadView('reports.cash_balances', ['data' => $reportData]);
                return $pdf->download('cash_balances.pdf');
            } elseif ($export === "excel") {
                return Excel::download(new CashBalancesReportExport($reportData), 'cash_balances.xlsx');
            }

            return JsonResponser::send(false, 'Cash Balances report generated successfully', $reportData, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal server error!', $th->getMessage(), 500);
        }
    }
}
