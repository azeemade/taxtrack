<?php

namespace App\Http\Controllers\v1\Company\Report\Transaction;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\FinanceAccountType\FinanceAccountTypeService;
use Maatwebsite\Excel\Facades\Excel;

class AccountTransactionController extends Controller
{
    protected FinanceAccountTypeService $financeAccountTypeService;

    public function __construct(FinanceAccountTypeService $financeAccountTypeService)
    {
        $this->financeAccountTypeService = $financeAccountTypeService;
    }

    public function index(Request $request)
    {
        try {
            $records = $this->financeAccountTypeService->accountTransactionReport($request);

            if ($request->export) {
                return $records;
            }
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, $th, [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}