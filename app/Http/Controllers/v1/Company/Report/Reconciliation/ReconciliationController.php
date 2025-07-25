<?php

namespace App\Http\Controllers\v1\Company\Report\Reconciliation;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Services\FinanceAccountType\FinanceAccountTypeService;
use Maatwebsite\Excel\Facades\Excel;

class ReconciliationController extends Controller
{

    protected FinanceAccountTypeService $financeAccountTypeService;

    public function __construct(FinanceAccountTypeService $financeAccountTypeService)
    {
        $this->financeAccountTypeService = $financeAccountTypeService;
    }

    public function accountSummary(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->accountSummary($request);

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

    public function bankReconciliationSummary(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->bankReconciliationSummary($request);

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

    public function trialBalance(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->trialBalanceReport($request);

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
}