<?php

namespace App\Services\Budget;

use App\Exceptions\BadRequestException;
use App\Exports\GeneralReportExport;
use App\Helpers\GeneralHelper;
use App\Models\Budget;
use App\Models\FinanceChartOfAccount;
use App\Services\FinanceAccountEntry\FinanceAccountEntryService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class BudgetService
{
    protected FinanceAccountEntryService $financeAccountEntryService;
    public function __construct(FinanceAccountEntryService $financeAccountEntryService)
    {
        $this->financeAccountEntryService = $financeAccountEntryService;
    }

    // public function create($request)
    // {
    //     $record = Budget::create([...$request, "budgetID" => $this->generateBudgetId()]);

    //     $record->budgetItems()->createMany($request['line_items']);

    //     $record->budgetItems->each(function ($item, $key) use ($request) {
    //         $item->periods()->createMany($request['line_items'][$key]['periods']);
    //     });

    //     return $record;
    // }

    public function create($request)
    {
        // Validate that periods align with cycle and duration
        $startDate = \Carbon\Carbon::parse($request['start_date']);
        $startYear = $startDate->year;
        $cycle = $request['cycle'];
        $duration = $request['duration'];

        foreach ($request['line_items'] as $index => $item) {
            foreach ($item['periods'] as $period) {
                $periodYear = (int) $period['year'];
                $periodMonth = strtolower($period['month']);
                // Validate year is within duration
                if ($periodYear < $startYear || $periodYear > $startYear + $duration - 1) {
                    throw new BadRequestException("Period year {$periodYear} is outside the budget duration.", 400);
                }
                // Validate month for monthly cycle
                if ($cycle === 'monthly' && !in_array($periodMonth, [
                    'january',
                    'february',
                    'march',
                    'april',
                    'may',
                    'june',
                    'july',
                    'august',
                    'september',
                    'october',
                    'november',
                    'december'
                ])) {
                    throw new BadRequestException("Invalid month {$periodMonth} for line item at index {$index}.", 400);
                }
            }
        }

        // Create Budget record
        $budgetData = [
            'name' => $request['name'],
            'status' => $request['status'],
            'start_date' => $request['start_date'],
            'cycle' => $request['cycle'],
            'duration' => $request['duration'],
            'description' => $request['description'] ?? null,
            'budgetID' => $this->generateBudgetId(),
            'company_id' => auth()->user()->current_company_id,
            'created_by' => auth()->user()->id,
        ];

        $budget = Budget::create($budgetData);

        // Create Budget Items and Periods
        foreach ($request['line_items'] as $item) {
            $budgetItem = $budget->budgetItems()->create([
                'account_id' => $item['account_id'],
                'company_id' => auth()->user()->current_company_id,
                'created_by' => auth()->user()->id,
            ]);

            $periods = array_map(function ($period) {
                return [
                    'month' => strtolower($period['month']),
                    'year' => $period['year'],
                    'amount' => (float) $period['amount'],
                    'company_id' => auth()->user()->current_company_id,
                    'created_by' => auth()->user()->id,
                ];
            }, $item['periods']);

            $budgetItem->periods()->createMany($periods);
        }

        // Load relationships for response
        $budget->load('budgetItems.periods', 'budgetItems.account');

        return $budget;
    }

    public function delete($id)
    {
        $record = Budget::find($id);

        $record->budgetItems->each(function ($item) {
            $item->periods()->delete();
        });

        $record->budgetItems()->delete();


        $record->delete();
    }

    public function view($id)
    {
        return Budget::select('id', 'name', 'status', 'start_date', 'cycle', 'duration', 'description')
            ->with([
                'budgetItems:id,name,category_id,budget_id' => ['periods:id,month,year,amount,budget_item_id', 'category:id,name'],
            ])
            ->find($id);
    }

    public function overview($request)
    {
        $records = Budget::select('id', 'name', 'status', 'start_date', 'cycle', 'duration', 'budgetID', 'created_at')
            ->when($request->q, function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->q . '%')
                    ->orWhere('cycle', 'like', '%' . $request->q . '%');
            })
            ->when($request->sort_by, function ($query) use ($request) {
                if ($request->sort_by == "alphabetically") {
                    return $query->orderBy('name', 'asc');
                } else if ($request->sort_by == "date_ascending") {
                    return $query->orderBy('start_date', 'asc');
                } else if ($request->sort_by == "date_descending") {
                    return $query->orderBy('start_date', 'desc');
                }
            })
            ->when(!empty($request->start_date) && !empty($request->end_date), function ($query) use ($request) {
                return $query->where('created_at', [$request->start_date, $request->end_date]);
            })
            ->latest();

        if (!$request->paginate) {
            return $records->get();
        }

        return $records->paginate($request->limit);
    }

    // public function update($request, int $id)
    // {
    //     $record = Budget::find($id);
    //     if (!$record) {
    //         throw new BadRequestException("Budget not found!", Response::HTTP_NOT_FOUND);
    //     }
    //     $record->update($request);

    //     $idsToKeep = [];
    //     foreach ($request['line_items'] as $lineItem) {
    //         unset($lineItem['periods']);
    //         if (isset($lineItem['id'])) {
    //             $idsToKeep[] = $lineItem['id'];
    //             $record->budgetItems()->where('id', $lineItem['id'])->update($lineItem);
    //         } else {
    //             $budgetItem = $record->budgetItems()->create($lineItem);
    //             $idsToKeep[] = $budgetItem['id'];
    //         }
    //     }
    //     $record->budgetItems()->whereNotIn('id', $idsToKeep)->delete();


    //     $record->budgetItems->each(function ($item, $key) use ($request) {
    //         $periodIdsToKeep = [];
    //         foreach ($request['line_items'][$key]['periods'] as $period) {
    //             if (isset($period['id'])) {
    //                 $periodIdsToKeep[] = $period['id'];
    //                 $item->periods()->where('id', $period['id'])->update($period);
    //             } else {
    //                 $newPeriod = $item->periods()->create($period);
    //                 $periodIdsToKeep[] = $newPeriod['id'];
    //             }
    //         }
    //         $item->periods()->whereNotIn('id', $periodIdsToKeep)->delete();
    //     });

    //     return $record;
    // }

    public function computePeriods($request)
    {
        $periods = [];
        $startDate = Carbon::parse($request->start_date);
        $duration = (int) $request->duration;

        $cycleDurations = [
            "monthly" => 1,
            "quarterly" => 4,
            "annually" => 12
        ];

        if (!isset($cycleDurations[$request->cycle])) {
            throw new BadRequestException("Invalid cycle", Response::HTTP_BAD_REQUEST);
        }

        $endDate = $startDate->copy()->addMonths($duration * $cycleDurations[$request->cycle]);

        while ($startDate->lte($endDate)) {
            $periods[] = [
                'month' => $startDate->format('F'),
                'year' => $startDate->format('Y')
            ];
            $startDate->addMonths($cycleDurations[$request->cycle]);
        }
        return $periods;
    }

    public function export($records, $exportType)
    {
        $recordHeadings = ['Budget name', 'ID', 'Created at', 'Start date', 'Duration', 'Cycle', 'Status', 'Budget items count', 'Total'];
        $records = $records->map(function ($record) {
            return [
                $record->name,
                $record->budgetID,
                Carbon::parse($record->created_at)->toFormattedDayDateString(),
                Carbon::parse($record->start_date)->toFormattedDayDateString(),
                $record->duration,
                $record->cycle,
                $record->status,
                $record->budgetItems->count(),
                $record->budget_total,
            ];
        });

        if ($exportType == 'pdf') {
            return Excel::download(new GeneralReportExport($records, $recordHeadings), 'card_transactions_report.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
        }
        return Excel::download(new GeneralReportExport($records, $recordHeadings), 'card_transactions_report.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function generateBudgetId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\Budget',
            "modelField" => 'budgetID',
            "prefix" => 'bid-',
            "idLength" => 5,
        ]);
    }


    public function getBudgetTemplate()
    {

        // Initialize template data
        $templateData = [];

        // Helper function to fetch accounts without budgets
        $fetchAccounts = function ($query) {
            return $query
                ->select('id', 'name', 'account_number', 'account_category_id', 'account_sub_category_id', 'company_id')
                ->where('company_id', auth()->user()->current_company_id) // Scope to companyy
                ->get()
                ->map(function ($account) {
                    return [
                        'account_id' => $account->id,
                        'account_name' => $account->name,
                        'account_number' => $account->account_number,
                    ];
                });
        };

        // 1. Sales: Accounts where subCategory slug is 'sales'
        $salesAccounts = $fetchAccounts(FinanceChartOfAccount::whereHas('subCategory', function ($query) {
            $query->where('slug', 'sales');
        }));
        $templateData['Sales'] = [
            'category_name' => 'Sales',
            'with_accounts' => true,
            'accounts' => $salesAccounts,
        ];

        // 2. Cost of Sales: Accounts where subCategory slug is 'cost-of-sales'
        $cosAccounts = $fetchAccounts(FinanceChartOfAccount::whereHas('subCategory', function ($query) {
            $query->where('slug', 'cost-of-sales');
        }));
        $templateData['CostOfSales'] = [
            'category_name' => 'Cost of Sales',
            'with_accounts' => true,
            'accounts' => $cosAccounts,
        ];

        // 3. Gross Profit: Calculated, no accounts
        $templateData['GrossProfit'] = [
            'category_name' => 'Gross Profit',
            'with_accounts' => false,
            'accounts' => [],
        ];

        // 4. Other Income: Accounts where accountType slug is 'income' and subCategory slug is not 'sales'
        $otherIncomeAccounts = $fetchAccounts(FinanceChartOfAccount::whereHas('accountType', function ($query) {
            $query->where('slug', 'income');
        })->whereHas('subCategory', function ($query) {
            $query->where('slug', '!=', 'sales');
        }));
        $templateData['OtherIncome'] = [
            'category_name' => 'Other Income',
            'with_accounts' => true,
            'accounts' => $otherIncomeAccounts,
        ];

        // 5. Expenses: Accounts where accountCategory slug is 'operating-expenses', excluding cost-of-sales and income-tax-payable
        $expenseAccounts = $fetchAccounts(FinanceChartOfAccount::whereHas('accountCategory', function ($query) {
            $query->where('slug', 'operating-expenses');
        })->whereDoesntHave('subCategory', function ($query) {
            $query->whereIn('slug', ['cost-of-sales', 'income-tax-payable']);
        }));
        $templateData['Expenses'] = [
            'category_name' => 'Expenses',
            'with_accounts' => true,
            'accounts' => $expenseAccounts,
        ];

        // 6. Income Tax: Accounts where subCategory slug is 'income-tax-payable'
        $incomeTaxAccounts = $fetchAccounts(FinanceChartOfAccount::whereHas('subCategory', function ($query) {
            $query->where('slug', 'income-tax-payable');
        }));

        $templateData['IncomeTax'] = [
            'category_name' => 'Income Tax',
            'with_accounts' => true,
            'accounts' => $incomeTaxAccounts,
        ];

        // 7. Net Profit Before Tax: Calculated, no accounts
        $templateData['NetProfitBeforeTax'] = [
            'category_name' => 'Net Profit Before Tax',
            'with_accounts' => false,
            'accounts' => [],
        ];

        // 8. Net Profit After Tax: Calculated, no accounts
        $templateData['NetProfitAfterTax'] = [
            'category_name' => 'Net Profit After Tax',
            'with_accounts' => false,
            'accounts' => [],
        ];

        return $templateData;
    }

    public function getBudget($budgetId)
    {
        // Fetch budget with items and periods, scoped to company
        $budget = Budget::with(['budgetItems.account', 'budgetItems.periods'])
            ->where('id', $budgetId)
            ->where('company_id', auth()->user()->current_company_id)
            ->firstOrFail();

        // Initialize response data
        $budgetData = [
            'budget' => [
                'id' => $budget->id,
                'name' => $budget->name,
                'status' => $budget->status,
                'start_date' => $budget->start_date,
                'cycle' => $budget->cycle,
                'duration' => $budget->duration,
                'description' => $budget->description,
                'budgetID' => $budget->budgetID,
                'company_id' => $budget->company_id,
                'created_by' => $budget->created_by,
                'created_at' => $budget->created_at,
                'updated_at' => $budget->updated_at,
            ],
            'categories' => [],
        ];

        // Helper function to fetch accounts and budgets
        $fetchAccountsWithBudgets = function ($query) use ($budget) {
            $accounts = $query->select('id', 'name', 'account_number')
                ->where('company_id', auth()->user()->current_company_id)
                ->get();

            return $accounts->map(function ($account) use ($budget) {
                // Find budget item for this account
                $budgetItem = $budget->budgetItems->firstWhere('account_id', $account->id);
                $monthlyBudgets = array_fill(1, 12, 0.00); // Jan to Dec
                $total = 0.00;

                if ($budgetItem && $budgetItem->periods) {
                    foreach ($budgetItem->periods as $period) {
                        $monthIndex = Carbon::parse("{$period->month} 1, {$period->year}")->month;
                        $monthlyBudgets[$monthIndex] = (float) $period->amount;
                        $total += (float) $period->amount;
                    }
                }

                return [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'account_number' => $account->account_number,
                    'monthly_budgets' => $monthlyBudgets,
                    'total' => $total,
                ];
            });
        };

        // 1. Sales: Accounts where subCategory slug is 'sales'
        $salesAccounts = $fetchAccountsWithBudgets(FinanceChartOfAccount::whereHas('subCategory', function ($query) {
            $query->where('slug', 'sales');
        }));
        $budgetData['categories']['Sales'] = [
            'category_name' => 'Sales',
            'with_accounts' => true,
            'accounts' => $salesAccounts,
            'total_monthly_budgets' => array_reduce($salesAccounts->toArray(), function ($carry, $account) {
                return array_map(fn($sum, $amount) => $sum + $amount, $carry, $account['monthly_budgets']);
            }, array_fill(1, 12, 0.00)),
            'total' => $salesAccounts->sum('total'),
        ];

        // 2. Cost of Sales: Accounts where subCategory slug is 'cost-of-sales'
        $cosAccounts = $fetchAccountsWithBudgets(FinanceChartOfAccount::whereHas('subCategory', function ($query) {
            $query->where('slug', 'cost-of-sales');
        }));
        $budgetData['categories']['CostOfSales'] = [
            'category_name' => 'Cost of Sales',
            'with_accounts' => true,
            'accounts' => $cosAccounts,
            'total_monthly_budgets' => array_reduce($cosAccounts->toArray(), function ($carry, $account) {
                return array_map(fn($sum, $amount) => $sum + $amount, $carry, $account['monthly_budgets']);
            }, array_fill(1, 12, 0.00)),
            'total' => $cosAccounts->sum('total'),
        ];

        // 3. Gross Profit: Sales - Cost of Sales
        $grossProfitMonthly = array_map(
            fn($sales, $cos) => $sales - $cos,
            $budgetData['categories']['Sales']['total_monthly_budgets'],
            $budgetData['categories']['CostOfSales']['total_monthly_budgets']
        );
        $budgetData['categories']['GrossProfit'] = [
            'category_name' => 'Gross Profit',
            'with_accounts' => false,
            'accounts' => [],
            'total_monthly_budgets' => $grossProfitMonthly,
            'total' => array_sum($grossProfitMonthly),
        ];

        // 4. Other Income: Accounts where accountType slug is 'income' and subCategory slug is not 'sales'
        $otherIncomeAccounts = $fetchAccountsWithBudgets(FinanceChartOfAccount::whereHas('accountType', function ($query) {
            $query->where('slug', 'income');
        })->whereHas('subCategory', function ($query) {
            $query->where('slug', '!=', 'sales');
        }));
        $budgetData['categories']['OtherIncome'] = [
            'category_name' => 'Other Income',
            'with_accounts' => true,
            'accounts' => $otherIncomeAccounts,
            'total_monthly_budgets' => array_reduce($otherIncomeAccounts->toArray(), function ($carry, $account) {
                return array_map(fn($sum, $amount) => $sum + $amount, $carry, $account['monthly_budgets']);
            }, array_fill(1, 12, 0.00)),
            'total' => $otherIncomeAccounts->sum('total'),
        ];

        // 5. Expenses: Accounts where accountCategory slug is 'operating-expenses', excluding cost-of-sales and income-tax-payable
        $expenseAccounts = $fetchAccountsWithBudgets(FinanceChartOfAccount::whereHas('accountCategory', function ($query) {
            $query->where('slug', 'operating-expenses');
        })->whereDoesntHave('subCategory', function ($query) {
            $query->whereIn('slug', ['cost-of-sales', 'income-tax-payable']);
        }));
        $budgetData['categories']['Expenses'] = [
            'category_name' => 'Expenses',
            'with_accounts' => true,
            'accounts' => $expenseAccounts,
            'total_monthly_budgets' => array_reduce($expenseAccounts->toArray(), function ($carry, $account) {
                return array_map(fn($sum, $amount) => $sum + $amount, $carry, $account['monthly_budgets']);
            }, array_fill(1, 12, 0.00)),
            'total' => $expenseAccounts->sum('total'),
        ];

        // 6. Income Tax: Accounts where subCategory slug is 'income-tax-payable'
        $incomeTaxAccounts = $fetchAccountsWithBudgets(FinanceChartOfAccount::whereHas('subCategory', function ($query) {
            $query->where('slug', 'income-tax-payable');
        }));
        $budgetData['categories']['IncomeTax'] = [
            'category_name' => 'Income Tax',
            'with_accounts' => true,
            'accounts' => $incomeTaxAccounts,
            'total_monthly_budgets' => array_reduce($incomeTaxAccounts->toArray(), function ($carry, $account) {
                return array_map(fn($sum, $amount) => $sum + $amount, $carry, $account['monthly_budgets']);
            }, array_fill(1, 12, 0.00)),
            'total' => $incomeTaxAccounts->sum('total'),
        ];

        // 7. Net Profit Before Tax: Gross Profit + Other Income - Expenses
        $netProfitBeforeTaxMonthly = array_map(
            fn($gp, $oi, $exp) => $gp + $oi - $exp,
            $budgetData['categories']['GrossProfit']['total_monthly_budgets'],
            $budgetData['categories']['OtherIncome']['total_monthly_budgets'],
            $budgetData['categories']['Expenses']['total_monthly_budgets']
        );
        $budgetData['categories']['NetProfitBeforeTax'] = [
            'category_name' => 'Net Profit Before Tax',
            'with_accounts' => false,
            'accounts' => [],
            'total_monthly_budgets' => $netProfitBeforeTaxMonthly,
            'total' => array_sum($netProfitBeforeTaxMonthly),
        ];

        // 8. Net Profit After Tax: Net Profit Before Tax - Income Tax
        $netProfitAfterTaxMonthly = array_map(
            fn($npbt, $tax) => $npbt - $tax,
            $budgetData['categories']['NetProfitBeforeTax']['total_monthly_budgets'],
            $budgetData['categories']['IncomeTax']['total_monthly_budgets']
        );
        $budgetData['categories']['NetProfitAfterTax'] = [
            'category_name' => 'Net Profit After Tax',
            'with_accounts' => false,
            'accounts' => [],
            'total_monthly_budgets' => $netProfitAfterTaxMonthly,
            'total' => array_sum($netProfitAfterTaxMonthly),
        ];

        return $budgetData;
    }


    public function update($budgetId, $request)
    {

        // Fetch budget, ensure it belongs to company
        $budget = Budget::where('id', $budgetId)
            ->where('company_id', auth()->user()->current_company_id)
            ->firstOrFail();

        // Validate periods if line_items provided
        if (isset($request['line_items'])) {
            $startDate = isset($request['start_date']) ? Carbon::parse($request['start_date']) : $budget->start_date;
            $startYear = $startDate->year;
            $cycle = $request['cycle'] ?? $budget->cycle;
            $duration = $request['duration'] ?? $budget->duration;

            foreach ($request['line_items'] as $index => $item) {
                foreach ($item['periods'] as $period) {
                    $periodYear = (int) $period['year'];
                    $periodMonth = strtolower($period['month']);
                    if ($periodYear < $startYear || $periodYear > $startYear + $duration - 1) {
                        throw new BadRequestException("Period year {$periodYear} is outside the budget duration.", 400);
                    }
                    if ($cycle === 'monthly' && !in_array($periodMonth, [
                        'january',
                        'february',
                        'march',
                        'april',
                        'may',
                        'june',
                        'july',
                        'august',
                        'september',
                        'october',
                        'november',
                        'december'
                    ])) {
                        throw new BadRequestException("Invalid month {$periodMonth} for line item at index {$index}.", 400);
                    }
                }
            }
        }

        // Update budget fields
        $budgetData = array_filter([
            'name' => $request['name'] ?? $budget->name,
            'status' => $request['status'] ?? $budget->status,
            'start_date' => $request['start_date'] ?? $budget->start_date,
            'cycle' => $request['cycle'] ?? $budget->cycle,
            'duration' => $request['duration'] ?? $budget->duration,
            'description' => $request['description'] ?? $budget->description,
        ]);

        $budget->update($budgetData);

        // Update line items and periods if provided
        if (isset($request['line_items'])) {
            // Delete existing budget items and periods
            $budget->budgetItems()->delete();

            // Create new budget items and periods
            foreach ($request['line_items'] as $item) {
                $budgetItem = $budget->budgetItems()->create([
                    'account_id' => $item['account_id'],
                    'company_id' => auth()->user()->current_company_id,
                    'created_by' => auth()->user()->id,
                ]);

                $periods = array_map(function ($period) {
                    return [
                        'month' => strtolower($period['month']),
                        'year' => $period['year'],
                        'amount' => (float) $period['amount'],
                        'company_id' => auth()->user()->current_company_id,
                        'created_by' => auth()->user()->id,
                    ];
                }, $item['periods']);

                $budgetItem->periods()->createMany($periods);
            }
        }

        // Load relationships for response
        $budget->load('budgetItems.periods', 'budgetItems.account');

        return $budget;
    }


    public function getBudgetVarianceReport($budgetId, $startDate, $endDate)
    {
        // Validate date range
        try {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();
        } catch (\Exception $e) {
            throw new BadRequestException('Invalid date format.', 400);
        }
        if ($start->gt($end)) {
            throw new BadRequestException('Start date must be before end date.', 400);
        }

        // Fetch budget with items and periods, scoped to company
        $budget = Budget::with(['budgetItems.account', 'budgetItems.periods'])
            ->where('id', $budgetId)
            ->where('company_id', auth()->user()->current_company_id)
            ->firstOrFail();

        // Initialize response data
        $budgetData = [
            'budget' => [
                'id' => $budget->id,
                'name' => $budget->name,
                'status' => $budget->status,
                'start_date' => $budget->start_date,
                'cycle' => $budget->cycle,
                'duration' => $budget->duration,
                'description' => $budget->description,
                'budgetID' => $budget->budgetID,
                'company_id' => $budget->company_id,
                'created_by' => $budget->created_by,
                'created_at' => $budget->created_at,
                'updated_at' => $budget->updated_at,
            ],
            'categories' => [],
        ];

        // Valid months for validation, mapping to zero-based indices
        $validMonths = [
            'january' => 0, 'february' => 1, 'march' => 2, 'april' => 3,
            'may' => 4, 'june' => 5, 'july' => 6, 'august' => 7,
            'september' => 8, 'october' => 9, 'november' => 10, 'december' => 11
        ];

        // Default monthly budget structure
        $defaultMonthlyBudget = [
            'actual' => 0.00,
            'budgeted' => 0.00,
            'variance' => 0.00,
            'variance_amount' => 0.00,
            'variance_percentage' => 0.00,
        ];

        // Helper function to fetch accounts and calculate actuals/budgets
        $fetchAccountsWithVariance = function ($query) use ($budget, $start, $end, $validMonths, $defaultMonthlyBudget) {
            $accounts = $query->select('id', 'name', 'account_number')
                ->where('company_id', auth()->user()->current_company_id)
                ->get();

            if ($accounts->isEmpty()) {
                Log::warning('No accounts found for query', ['query' => $query->toSql()]);
                return collect([]);
            }

            return $accounts->map(function ($account) use ($budget, $start, $end, $validMonths, $defaultMonthlyBudget) {
                // Get actual amounts
                try {
                    $accountBalance = $this->financeAccountEntryService->getAccountBalanceWithFlow(
                        $account->id,
                        $start->toDateString(),
                        $end->toDateString()
                    );
                    $actualAmount = $accountBalance['total_inflow'] ?? 0.00;
                } catch (\Exception $e) {
                    Log::error("Failed to get account balance for account {$account->id}: {$e->getMessage()}");
                    $actualAmount = 0.00;
                }

                // Initialize monthly budgets as a sequential array
                $monthlyBudgets = array_fill(0, 12, $defaultMonthlyBudget);

                // Get budget periods
                $budgetItem = $budget->budgetItems->firstWhere('account_id', $account->id);
                $total = $defaultMonthlyBudget;

                if ($budgetItem && $budgetItem->periods) {
                    // Calculate months in range, ensuring at least 1
                    $monthsInRange = max(1, $start->diffInMonths($end) + 1);
                    $actualPerMonth = $actualAmount / $monthsInRange;

                    foreach ($budgetItem->periods as $period) {
                        $monthName = strtolower($period->month ?? '');
                        if (!isset($validMonths[$monthName])) {
                            Log::warning("Invalid month '{$monthName}' for budget item {$budgetItem->id}, period {$period->id}");
                            continue;
                        }

                        try {
                            $periodDate = Carbon::parse("{$monthName} 1, {$period->year}");
                            if (!$periodDate->between($start, $end)) {
                                continue;
                            }
                            $monthIndex = $validMonths[$monthName];
                        } catch (\Exception $e) {
                            Log::warning("Failed to parse period date for month '{$monthName}', year '{$period->year}': {$e->getMessage()}");
                            continue;
                        }

                        $budgeted = (float) ($period->amount ?? 0.00);
                        $variance = $actualPerMonth - $budgeted;
                        $varianceAmount = abs($variance);
                        $variancePercentage = $budgeted != 0 ? ($variance / $budgeted) * 100 : 0.00;

                        $monthlyBudgets[$monthIndex] = [
                            'actual' => round($actualPerMonth, 2),
                            'budgeted' => $budgeted,
                            'variance' => round($variance, 2),
                            'variance_amount' => round($varianceAmount, 2),
                            'variance_percentage' => round($variancePercentage, 2),
                        ];

                        $total['actual'] += $actualPerMonth;
                        $total['budgeted'] += $budgeted;
                        $total['variance'] += $variance;
                        $total['variance_amount'] += $varianceAmount;
                    }

                    $total['variance_percentage'] = $total['budgeted'] != 0
                        ? ($total['variance'] / $total['budgeted']) * 100
                        : 0.00;
                    $total = array_map(fn($value) => round($value, 2), $total);
                }

                return [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'account_number' => $account->account_number,
                    'monthly_budgets' => array_values($monthlyBudgets), // Ensure sequential array
                    'total' => $total,
                ];
            });
        };

        // 1. Sales
        $salesAccounts = $fetchAccountsWithVariance(FinanceChartOfAccount::whereHas('subCategory', function ($query) {
            $query->where('slug', 'sales');
        }));
        $budgetData['categories']['Sales'] = [
            'category_name' => 'Sales',
            'with_accounts' => true,
            'accounts' => $salesAccounts,
            'total_monthly_budgets' => $this->aggregateCategoryTotals($salesAccounts),
            'total' => $this->sumTotals($salesAccounts),
        ];

        // 2. Cost of Sales
        $cosAccounts = $fetchAccountsWithVariance(FinanceChartOfAccount::whereHas('subCategory', function ($query) {
            $query->where('slug', 'cost-of-sales');
        }));
        $budgetData['categories']['CostOfSales'] = [
            'category_name' => 'Cost of Sales',
            'with_accounts' => true,
            'accounts' => $cosAccounts,
            'total_monthly_budgets' => $this->aggregateCategoryTotals($cosAccounts),
            'total' => $this->sumTotals($cosAccounts),
        ];

        // 3. Gross Profit: Sales - Cost of Sales
        $grossProfitMonthly = $this->calculateDerivedCategory(
            [
                $budgetData['categories']['Sales']['total_monthly_budgets'],
                $budgetData['categories']['CostOfSales']['total_monthly_budgets'],
            ],
            true,
            fn($sales, $cos) => [
                'actual' => ($sales['actual'] ?? 0.00) - ($cos['actual'] ?? 0.00),
                'budgeted' => ($sales['budgeted'] ?? 0.00) - ($cos['budgeted'] ?? 0.00),
                'variance' => (($sales['actual'] ?? 0.00) - ($cos['actual'] ?? 0.00)) - (($sales['budgeted'] ?? 0.00) - ($cos['budgeted'] ?? 0.00)),
                'variance_amount' => abs((($sales['actual'] ?? 0.00) - ($cos['actual'] ?? 0.00)) - (($sales['budgeted'] ?? 0.00) - ($cos['budgeted'] ?? 0.00))),
                'variance_percentage' => (($sales['budgeted'] ?? 0.00) - ($cos['budgeted'] ?? 0.00)) != 0
                    ? (((($sales['actual'] ?? 0.00) - ($cos['actual'] ?? 0.00)) - (($sales['budgeted'] ?? 0.00) - ($cos['budgeted'] ?? 0.00))) / (($sales['budgeted'] ?? 0.00) - ($cos['budgeted'] ?? 0.00))) * 100
                    : 0.00,
            ]
        );
        $budgetData['categories']['GrossProfit'] = [
            'category_name' => 'Gross Profit',
            'with_accounts' => false,
            'accounts' => [],
            'total_monthly_budgets' => $grossProfitMonthly,
            'total' => $this->sumTotals([$grossProfitMonthly], true),
        ];

        // 4. Other Income
        $otherIncomeAccounts = $fetchAccountsWithVariance(FinanceChartOfAccount::whereHas('accountType', function ($query) {
            $query->where('slug', 'income');
        })->whereHas('subCategory', function ($query) {
            $query->where('slug', '!=', 'sales');
        }));
        $budgetData['categories']['OtherIncome'] = [
            'category_name' => 'Other Income',
            'with_accounts' => true,
            'accounts' => $otherIncomeAccounts,
            'total_monthly_budgets' => $this->aggregateCategoryTotals($otherIncomeAccounts),
            'total' => $this->sumTotals($otherIncomeAccounts),
        ];

        // 5. Expenses
        $expenseAccounts = $fetchAccountsWithVariance(FinanceChartOfAccount::whereHas('accountCategory', function ($query) {
            $query->where('slug', 'operating-expenses');
        })->whereDoesntHave('subCategory', function ($query) {
            $query->whereIn('slug', ['cost-of-sales', 'income-tax-payable']);
        }));
        $budgetData['categories']['Expenses'] = [
            'category_name' => 'Expenses',
            'with_accounts' => true,
            'accounts' => $expenseAccounts,
            'total_monthly_budgets' => $this->aggregateCategoryTotals($expenseAccounts),
            'total' => $this->sumTotals($expenseAccounts),
        ];

        // 6. Income Tax
        $incomeTaxAccounts = $fetchAccountsWithVariance(FinanceChartOfAccount::whereHas('subCategory', function ($query) {
            $query->where('slug', 'income-tax-payable');
        }));
        $budgetData['categories']['IncomeTax'] = [
            'category_name' => 'Income Tax',
            'with_accounts' => true,
            'accounts' => $incomeTaxAccounts,
            'total_monthly_budgets' => $this->aggregateCategoryTotals($incomeTaxAccounts),
            'total' => $this->sumTotals($incomeTaxAccounts),
        ];

        // 7. Net Profit Before Tax: Gross Profit + Other Income - Expenses
        $netProfitBeforeTaxMonthly = $this->calculateDerivedCategory(
            [
                $budgetData['categories']['GrossProfit']['total_monthly_budgets'],
                $budgetData['categories']['OtherIncome']['total_monthly_budgets'],
                $budgetData['categories']['Expenses']['total_monthly_budgets'],
            ],
            false,
            fn($gp, $oi, $exp) => [
                'actual' => ($gp['actual'] ?? 0.00) + ($oi['actual'] ?? 0.00) - ($exp['actual'] ?? 0.00),
                'budgeted' => ($gp['budgeted'] ?? 0.00) + ($oi['budgeted'] ?? 0.00) - ($exp['budgeted'] ?? 0.00),
                'variance' => (($gp['actual'] ?? 0.00) + ($oi['actual'] ?? 0.00) - ($exp['actual'] ?? 0.00)) - (($gp['budgeted'] ?? 0.00) + ($oi['budgeted'] ?? 0.00) - ($exp['budgeted'] ?? 0.00)),
                'variance_amount' => abs((($gp['actual'] ?? 0.00) + ($oi['actual'] ?? 0.00) - ($exp['actual'] ?? 0.00)) - (($gp['budgeted'] ?? 0.00) + ($oi['budgeted'] ?? 0.00) - ($exp['budgeted'] ?? 0.00))),
                'variance_percentage' => (($gp['budgeted'] ?? 0.00) + ($oi['budgeted'] ?? 0.00) - ($exp['budgeted'] ?? 0.00)) != 0
                    ? (((($gp['actual'] ?? 0.00) + ($oi['actual'] ?? 0.00) - ($exp['actual'] ?? 0.00)) - (($gp['budgeted'] ?? 0.00) + ($oi['budgeted'] ?? 0.00) - ($exp['budgeted'] ?? 0.00))) / (($gp['budgeted'] ?? 0.00) + ($oi['budgeted'] ?? 0.00) - ($exp['budgeted'] ?? 0.00))) * 100
                    : 0.00,
            ]
        );
        $budgetData['categories']['NetProfitBeforeTax'] = [
            'category_name' => 'Net Profit Before Tax',
            'with_accounts' => false,
            'accounts' => [],
            'total_monthly_budgets' => $netProfitBeforeTaxMonthly,
            'total' => $this->sumTotals([$netProfitBeforeTaxMonthly], true),
        ];

        // 8. Net Profit After Tax: Net Profit Before Tax - Income Tax
        $netProfitAfterTaxMonthly = $this->calculateDerivedCategory(
            [
                $budgetData['categories']['NetProfitBeforeTax']['total_monthly_budgets'],
                $budgetData['categories']['IncomeTax']['total_monthly_budgets'],
            ],
            true,
            fn($npbt, $tax) => [
                'actual' => ($npbt['actual'] ?? 0.00) - ($tax['actual'] ?? 0.00),
                'budgeted' => ($npbt['budgeted'] ?? 0.00) - ($tax['budgeted'] ?? 0.00),
                'variance' => (($npbt['actual'] ?? 0.00) - ($tax['actual'] ?? 0.00)) - (($npbt['budgeted'] ?? 0.00) - ($tax['budgeted'] ?? 0.00)),
                'variance_amount' => abs((($npbt['actual'] ?? 0.00) - ($tax['actual'] ?? 0.00)) - (($npbt['budgeted'] ?? 0.00) - ($tax['budgeted'] ?? 0.00))),
                'variance_percentage' => (($npbt['budgeted'] ?? 0.00) - ($tax['budgeted'] ?? 0.00)) != 0
                    ? (((($npbt['actual'] ?? 0.00) - ($tax['actual'] ?? 0.00)) - (($npbt['budgeted'] ?? 0.00) - ($tax['budgeted'] ?? 0.00))) / (($npbt['budgeted'] ?? 0.00) - ($tax['budgeted'] ?? 0.00))) * 100
                    : 0.00,
            ]
        );
        $budgetData['categories']['NetProfitAfterTax'] = [
            'category_name' => 'Net Profit After Tax',
            'with_accounts' => false,
            'accounts' => [],
            'total_monthly_budgets' => $netProfitAfterTaxMonthly,
            'total' => $this->sumTotals([$netProfitAfterTaxMonthly], true),
        ];

        return $budgetData;
    }

    protected function aggregateCategoryTotals($accounts)
    {
        $initial = array_fill(0, 12, [
            'actual' => 0.00,
            'budgeted' => 0.00,
            'variance' => 0.00,
            'variance_amount' => 0.00,
            'variance_percentage' => 0.00,
        ]);

        if ($accounts->isEmpty()) {
            Log::warning('No accounts provided to aggregateCategoryTotals');
            return $initial;
        }

        $result = array_reduce($accounts->toArray(), function ($carry, $account) {
            if (!isset($account['monthly_budgets']) || !is_array($account['monthly_budgets'])) {
                Log::warning('Invalid monthly_budgets for account', ['account_id' => $account['account_id'] ?? null]);
                return $carry;
            }

            return array_map(function ($sum, $month, $monthIndex) use ($account) {
                if (!isset($month['actual'])) {
                    Log::warning("Missing 'actual' key in monthly_budgets for account {$account['account_id']} at index {$monthIndex}");
                    $month = array_merge([
                        'actual' => 0.00,
                        'budgeted' => 0.00,
                        'variance' => 0.00,
                        'variance_amount' => 0.00,
                        'variance_percentage' => 0.00,
                    ], $month);
                }

                $sum['actual'] += $month['actual'];
                $sum['budgeted'] += $month['budgeted'];
                $sum['variance'] += $month['variance'];
                $sum['variance_amount'] += $month['variance_amount'];
                $sum['variance_percentage'] = $sum['budgeted'] != 0
                    ? ($sum['variance'] / $sum['budgeted']) * 100
                    : 0.00;
                return array_map(fn($value) => round($value, 2), $sum);
            }, $carry, $account['monthly_budgets'], array_keys($account['monthly_budgets']));
        }, $initial);

        Log::debug('aggregateCategoryTotals result', ['result' => $result]);

        return array_values($result); // Ensure sequential array
    }

    protected function sumTotals($items, $isArray = false)
    {
        $totals = ['actual' => 0.00, 'budgeted' => 0.00, 'variance' => 0.00, 'variance_amount' => 0.00, 'variance_percentage' => 0.00];
        foreach ($items as $item) {
            $itemTotals = $isArray ? $item : ($item['total'] ?? $totals);
            $totals['actual'] += $itemTotals['actual'] ?? 0.00;
            $totals['budgeted'] += $itemTotals['budgeted'] ?? 0.00;
            $totals['variance'] += $itemTotals['variance'] ?? 0.00;
            $totals['variance_amount'] += $itemTotals['variance_amount'] ?? 0.00;
        }
        $totals['variance_percentage'] = $totals['budgeted'] != 0
            ? ($totals['variance'] / $totals['budgeted']) * 100
            : 0.00;
        return array_map(fn($value) => round($value, 2), $totals);
    }

    protected function calculateDerivedCategory($inputs, $subtract, $callback)
    {
        $default = [
            'actual' => 0.00,
            'budgeted' => 0.00,
            'variance' => 0.00,
            'variance_amount' => 0.00,
            'variance_percentage' => 0.00,
        ];
        $result = array_fill(0, 12, $default);

        // Validate inputs
        $required = $subtract ? 2 : 3;
        if (count($inputs) < $required) {
            Log::error("Insufficient inputs for calculateDerivedCategory", ['required' => $required, 'provided' => count($inputs)]);
            return $result;
        }

        foreach ($inputs as $index => $input) {
            if (!is_array($input) || count($input) !== 12) {
                Log::warning("Invalid input at index {$index} for calculateDerivedCategory", ['input' => $input]);
                $inputs[$index] = array_fill(0, 12, $default);
            }
        }

        // Log::debug('calculateDerivedCategory inputs', ['inputs' => $inputs]);

        foreach (array_keys($result) as $monthIndex) {
            try {
                $monthInputs = array_map(fn($input) => $input[$monthIndex] ?? $default, $inputs);
                if ($subtract) {
                    $result[$monthIndex] = $callback($monthInputs[0], $monthInputs[1]);
                } else {
                    $result[$monthIndex] = $callback($monthInputs[0], $monthInputs[1], $monthInputs[2]);
                }
                $result[$monthIndex] = array_map(fn($value) => round($value, 2), $result[$monthIndex]);
            } catch (\Exception $e) {
                Log::error("Error in calculateDerivedCategory for index {$monthIndex}: {$e->getMessage()}");
                $result[$monthIndex] = $default;
            }
        }

        return array_values($result); // Ensure sequential array
    }
}
