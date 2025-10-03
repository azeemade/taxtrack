<?php

namespace App\Http\Controllers\v1\Company\Report\VAT;

use App\Http\Controllers\Controller;
use App\Responser\JsonResponser;
use App\Services\Report\VatReturnService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VATReturnController extends Controller
{
    protected VatReturnService $vATReturnService;

    public function __construct(VatReturnService $vATReturnService)
    {
        $this->vATReturnService = $vATReturnService;
    }

    public function generateFlatRateVatReturn(Request $request)
    {
        try {
            $userCompanyId = auth()->user()->current_company_id;
            $vatNumber = auth()->user()->company->tax_id;

            $startDate = $request->start_date ?? now()->startOfYear()->toDateString();
            $endDate = $request->end_date ?? now()->endOfYear()->toDateString();

            $records = $this->vATReturnService->generateFlatRateVatReturn($startDate, $endDate, $userCompanyId, $vatNumber);

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function generateStandardRateVatReturn(Request $request)
    {
        try {
            $userCompanyId = auth()->user()->current_company_id;
            $vatNumber = auth()->user()->company->tax_id;

            $startDate = $request->start_date ?? now()->startOfYear()->toDateString();
            $endDate = $request->end_date ?? now()->endOfYear()->toDateString();

            $records = $this->vATReturnService->generateStandardRateVatReturn($startDate, $endDate, $userCompanyId, $vatNumber);

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }


    public function generateVatReturn(Request $request)
    {
        try {
            $userCompanyId = auth()->user()->current_company_id;
            $vatNumber = auth()->user()->company->tax_id;

            $startDate = $request->start_date ?? now()->startOfYear()->toDateString();
            $endDate = $request->end_date ?? now()->endOfYear()->toDateString();

            if (auth()->user()->company->tax_type == 'standard' || auth()->user()->company->tax_type == "standard type") {
                $records = $this->vATReturnService->generateStandardRateVatReturn($startDate, $endDate, $userCompanyId, $vatNumber, 20);
            } else {
                $records = $this->vATReturnService->generateFlatRateVatReturn($startDate, $endDate, $userCompanyId, $vatNumber, auth()->user()->company->tax_type);
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, "Internal Server Error", null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
