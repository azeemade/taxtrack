<?php

namespace App\Http\Controllers\v1\Admin\Report;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Http\Resources\CompanyResource;
use App\Responser\JsonResponser;
use App\Services\Report\AdminReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{

    protected AdminReportService $adminReportService;

    public function __construct(AdminReportService $adminReportService)
    {
        $this->adminReportService = $adminReportService;
    }

    /**
     * Display a listing of the resource.
     */
    public function companies(SharedFilterRequest $request)
    {
        try {
            $records = $this->adminReportService->companies($request);

            if ($request->export) {
                return $this->adminReportService->exportCompanies($records, $request->export);
            }

            if ($request->paginate) CompanyResource::collection($records);

            return JsonResponser::send(false, 'Record(s) found successfully', $records);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', null, Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
