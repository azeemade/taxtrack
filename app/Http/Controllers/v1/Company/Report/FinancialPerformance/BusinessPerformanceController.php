<?php

namespace App\Http\Controllers\v1\Company\Report\FinancialPerformance;

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

class BusinessPerformanceController extends Controller
{
    protected FinanceAccountTypeService $financeAccountTypeService;

    public function __construct(FinanceAccountTypeService $financeAccountTypeService)
    {
        $this->financeAccountTypeService = $financeAccountTypeService;
    }

    public function getLiabilityToNetWorthRatio(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->getLiabilityToNetWorthRatio($request);

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

    public function getDebtToEquityRatio(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->getDebtToEquityRatio($request);

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

    public function getFixedAssetToNetWorthRatio(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->getFixedAssetToNetWorthRatio($request);

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

    public function getGrossProfitPercentage(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->getGrossProfitPercentage($request);

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

    public function getNetProfitOnNetSales(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->getNetProfitOnNetSales($request);

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

    public function getWorkingCapitalToTotalAssets(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->getWorkingCapitalToTotalAssets($request);

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

    public function getBusinessSnapshotSummary(Request $request)
    {
        try {
            $getLiabilityToNetWorthRatio = $this->financeAccountTypeService->getLiabilityToNetWorthRatio($request);
            $getDebtToEquityRatio = $this->financeAccountTypeService->getDebtToEquityRatio($request);
            $getFixedAssetToNetWorthRatio = $this->financeAccountTypeService->getFixedAssetToNetWorthRatio($request);
            $getGrossProfitPercentage = $this->financeAccountTypeService->getGrossProfitPercentage($request);
            $getNetProfitOnNetSales = $this->financeAccountTypeService->getNetProfitOnNetSales($request);
            $getWorkingCapitalToTotalAssets = $this->financeAccountTypeService->getWorkingCapitalToTotalAssets($request);


            $records = [
                'getLiabilityToNetWorthRatio' => $getLiabilityToNetWorthRatio,
                'getDebtToEquityRatio' => $getDebtToEquityRatio,
                'getFixedAssetToNetWorthRatio' => $getFixedAssetToNetWorthRatio,
                'getGrossProfitPercentage' => $getGrossProfitPercentage,
                'getNetProfitOnNetSales' => $getNetProfitOnNetSales,
                'getWorkingCapitalToTotalAssets' => $getWorkingCapitalToTotalAssets,
            ];

           
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

  
}
