<?php

namespace App\Http\Controllers\v1\Admin\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\CreateCompanyRequest;
use App\Responser\JsonResponser;
use App\Services\CompanyServices\CompanyService;
use Illuminate\Http\Request;

class CompanyManagementController extends Controller
{
    public function create(CreateCompanyRequest $request, CompanyService $companyService)
    {
        try {
            $record = $companyService->create($request->validated());

            return JsonResponser::send(false, 'Company created successfully', $record, 200);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', $th->getMessage(), 500);
        }
    }
}
