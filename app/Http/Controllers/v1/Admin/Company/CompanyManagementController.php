<?php

namespace App\Http\Controllers\v1\Admin\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\AttachUserRequest;
use App\Http\Requests\Company\CreateCompanyRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Responser\JsonResponser;
use App\Services\CompanyServices\CompanyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompanyManagementController extends Controller
{
    protected CompanyService $companyService;

    public function __construct(CompanyService $companyService)
    {
        $this->companyService = $companyService;
    }

    public function overview(SharedFilterRequest $request)
    {
        try {
            $overview = $this->companyService->overview($request);

            $stats = $this->companyService->stats();
            $records = [
                ...$stats,
                'data' => $overview
            ];
            if ($request->export) {
                return $this->companyService->export($overview);
            }
            if (!$request->paginate) {
                $records = $overview;
            }

            return JsonResponser::send(false, 'Company(s) retrieved successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }

    public function create(CreateCompanyRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->companyService->create($request->validated());

            DB::commit();
            return JsonResponser::send(false, 'Company created successfully', $record);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }

    public function attachUser(AttachUserRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->companyService->attachUser($request->validated());

            DB::commit();
            return JsonResponser::send(false, 'User attached to company successfully', $record, 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], 500);
        }
    }
}
