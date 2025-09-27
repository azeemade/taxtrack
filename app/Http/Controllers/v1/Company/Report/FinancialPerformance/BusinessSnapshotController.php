<?php

namespace App\Http\Controllers\v1\Company\Report\FinancialPerformance;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\FinanceAccountType\FinanceAccountTypeService;

class BusinessSnapshotController extends Controller
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

    public function getIncomeReport(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->getIncomeReport($request);

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

    public function getExpensesReport(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->getExpensesReport($request);

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

    public function getNetProfitMarginReport(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->getNetProfitMarginReport($request);

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

    public function getBalanceSheetReport(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->getBalanceSheetReport($request);

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

    public function getCashBalancesReport(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->getCashBalancesReport($request);

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

    public function getOperatingExpensesReport(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->getOperatingExpensesReport($request);

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

    public function getAverageTime(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->getAverageTime($request);

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

    public function businessSnapshotSummary(Request $request)
    {
        try {
            // Initialize default values
            $records = [
                'profitOrLossSummary' => [
                    'netProfitCurrent' => 0,
                    'netProfitPrevious' => 0,
                ],
                'incomeSummary' => [],
                'expenseSummary' => [],
                'netProfitMarginSummary' => [],
                'averageTimeSummary' => [
                    'averageDayToGetPaid' => 0,
                    'valueOfUnpaidInvoices' => 0,
                    'averageDayToToPaySuppliers' => 0,
                    'valueOfUnpaidBills' => 0,
                ],
                'cashBalancesSummary' => [
                    'accounts' => [],
                    'totalCurrent' => 0,
                    'totalPrevious' => 0,
                ],
                'operatingExpensesBalancesSummary' => [
                    'accounts' => [],
                    'totalCurrent' => 0,
                    'totalPrevious' => 0,
                ],
                'balanceSheetSummary' => [],
            ];

            $dataRange = $this->financeAccountTypeService->dateRangeCalculatorOne($request);
            

            $getProfitAndLossReport = $this->financeAccountTypeService->getProfitAndLossReport($request);
            if (isset($getProfitAndLossReport['netProfitCurrent']) && isset($getProfitAndLossReport['netProfitPrevious'])) {
                $records['profitOrLossSummary'] = [
                    'netProfitCurrent' => $profitAndLossData['netProfitCurrent'] ?? 0,
                    'netProfitPrevious' => $profitAndLossData['netProfitPrevious'] ?? 0,
                ];
            }

            $getIncomeReport = $this->financeAccountTypeService->getIncomeReport($request);
            if (isset($getIncomeReport['months']) && is_array($getIncomeReport['months'])) {
                $records['incomeSummary'] = [
                    'months' => collect($getIncomeReport['months'])->map(function ($month) {
                        return [
                            'name' => $month['name'] ?? '',
                            'total' => $month['total'] ?? 0,
                            // 'categories' => collect($month['categories'] ?? [])->map(
                            //     function ($category) {
                            //         return [
                            //             'name' => $category['name'] ?? '',
                            //             'total' => $category['total'] ?? 0,
                            //         ];
                            //     }
                            // )->toArray(),
                        ];
                    })->toArray(),
                    'totalRevenue' => $getIncomeReport['totalRevenue'] ?? 0,
                    'categoryTotals' => $getIncomeReport['categoryTotals'] ?? [],
                ];
            }

            $getExpensesReport = $this->financeAccountTypeService->getExpensesReport($request);
            if (isset($getExpensesReport['months']) && is_array($getExpensesReport['months'])) {
                $records['expenseSummary'] = [
                    'months' => collect($getExpensesReport['months'])->map(function ($month) {
                        return [
                            'name' => $month['name'] ?? '',
                            'total' => $month['total'] ?? 0,
                            // 'categories' => collect($month['categories'] ?? [])->map(
                            //     function ($category) {
                            //         return [
                            //             'name' => $category['name'] ?? '',
                            //             'total' => $category['total'] ?? 0,
                            //         ];
                            //     }
                            // )->toArray(),
                        ];
                    })->toArray(),
                    'totalExpense' => $getExpensesReport['totalExpense'] ?? 0,
                    'categoryTotals' => $getExpensesReport['categoryTotals'] ?? [],
                ];
            }

            $getNetProfitMarginReport = $this->financeAccountTypeService->getNetProfitMarginReport($request);
            if (isset($getNetProfitMarginReport['months']) && is_array($getNetProfitMarginReport['months'])) {
                $records['netProfitMarginSummary'] = [
                    'months' => collect($getNetProfitMarginReport['months'])->map(function ($month) {
                        return [
                            'name' => $month['name'] ?? '',
                            'margin' => $month['margin'] ?? 0,
                            'percentage' => $month['percentage'] ?? 0,
                        ];
                    })->toArray(),
                    'currentPercentage' => $getNetProfitMarginReport['currentPercentage'] ?? 0,
                ];
            }

            $getBalanceSheetReport = $this->financeAccountTypeService->getBalanceSheetReport($request);
            if (isset($getBalanceSheetReport['balanceSheet']) && is_array($getBalanceSheetReport['balanceSheet'])) {
                $records['balanceSheetSummary'] = collect($getBalanceSheetReport['balanceSheet'])->map(function ($balanceSheet) {
                    return [
                        'name' => $balanceSheet['name'] ?? '',
                        'totalCurrent' => $balanceSheet['totalCurrent'] ?? 0,
                        'totalPrevious' => $balanceSheet['totalPrevious'] ?? 0,
                    ];
                })->toArray();
            }

            $getCashBalancesReport = $this->financeAccountTypeService->getCashBalancesReport($request);
            if (isset($getCashBalancesReport['accounts']) && is_array($getCashBalancesReport['accounts'])) {
                $records['cashBalancesSummary'] = [
                    'accounts' => collect($getCashBalancesReport['accounts'])->map(function ($account) {
                        return [
                            'name' => $account['name'] ?? '',
                            'current' => $account['current'] ?? 0,
                            'previous' => $account['previous'] ?? 0,
                        ];
                    })->toArray(),
                    'totalCurrent' => $getCashBalancesReport['totalCurrent'] ?? 0,
                    'totalPrevious' => $getCashBalancesReport['totalPrevious'] ?? 0,
                ];
            }

            $getOperatingExpensesReport = $this->financeAccountTypeService->getOperatingExpensesReport($request);
            if (isset($getOperatingExpensesReport['accounts']) && is_array($getOperatingExpensesReport['accounts'])) {
                $records['operatingExpensesBalancesSummary'] = [
                    'accounts' => collect($getOperatingExpensesReport['accounts'])->map(function ($account) {
                        return [
                            'name' => $account['name'] ?? '',
                            'current' => $account['current'] ?? 0,
                            'previous' => $account['previous'] ?? 0,
                        ];
                    })->toArray(),
                    'totalCurrent' => $getOperatingExpensesReport['totalCurrent'] ?? 0,
                    'totalPrevious' => $getOperatingExpensesReport['totalPrevious'] ?? 0,
                ];
            }

            $averageTimeData  = $this->financeAccountTypeService->getAverageTime($request);
            if (isset($averageTimeData['averageDayToGetPaid']) && isset($getProfitAndLossReport['valueOfUnpaidInvoices']) && isset($getProfitAndLossReport['averageDayToToPaySuppliers']) && isset($getProfitAndLossReport['valueOfUnpaidBills'])) {
                $records['averageTimeSummary'] = [
                    'averageDayToGetPaid' => $averageTimeData['averageDayToGetPaid'] ?? 0,
                    'valueOfUnpaidInvoices' => $averageTimeData['valueOfUnpaidInvoices'] ?? 0,
                    'averageDayToToPaySuppliers' => $averageTimeData['averageDayToToPaySuppliers'] ?? 0,
                    'valueOfUnpaidBills' => $averageTimeData['valueOfUnpaidBills'] ?? 0,
                ];
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th, [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
