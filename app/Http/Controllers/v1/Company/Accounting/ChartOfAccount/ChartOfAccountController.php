<?php

namespace App\Http\Controllers\v1\Company\Accounting\ChartOfAccount;

use App\Exceptions\BadRequestException;
use App\Exports\Accounting\ChartOfAccount\ChartOfAccountDownloadTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Accounting\ChartOfAccount\StoreChartOfAccountRequest;
use App\Http\Requests\Company\Accounting\ChartOfAccount\UpdateChartOfAccountRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Models\FinanceAccountEntry;
use App\Responser\JsonResponser;
use App\Services\ChartOfAccount\ChartOfAccountService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Imports\Accounting\ChartOfAccount\ChartOfAccountImport;
use Maatwebsite\Excel\Facades\Excel;

class ChartOfAccountController extends Controller
{
    protected ChartOfAccountService $chartOfAccountService;

    public function __construct(ChartOfAccountService $chartOfAccountService)
    {
        $this->chartOfAccountService = $chartOfAccountService;
    }


    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->chartOfAccountService->allChartOfAccount($request);
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    private function calculateYearlyBalance($accountId, $year)
    {
        $debitSum = FinanceAccountEntry::where('account_id', $accountId)
            ->whereYear('date', $year)
            ->sum('debit_amount');

        $creditSum = FinanceAccountEntry::where('account_id', $accountId)
            ->whereYear('date', $year)
            ->sum('credit_amount');

        return $debitSum - $creditSum;
    }

    public function allChartOfAccountNotPaginated()
    {
        try {
            $records = $this->chartOfAccountService->allChartOfAccountNotPaginated();
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function allSubCategoriesNotPaginated()
    {
        try {
            $records = $this->chartOfAccountService->allSubCategoriesNotPaginated();
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function createAccount(StoreChartOfAccountRequest $request)
    {
        try {
            $record = $this->chartOfAccountService->createAccount($request);
            return JsonResponser::send(false, 'Account created successfully!', $record, Response::HTTP_CREATED);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function show($id)
    {
        try {
            $records = $this->chartOfAccountService->show($id);
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function update(UpdateChartOfAccountRequest $request, $id)
    {
        try {
            $records = $this->chartOfAccountService->updateAccount($request, $id);
            return JsonResponser::send(false, 'Record updated successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], Response::HTTP_CONFLICT);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function delete($id)
    {
        try {
            $records = $this->chartOfAccountService->delete($id);
            return JsonResponser::send(false, 'Record deleted successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], Response::HTTP_CONFLICT);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function toggleStatus($id)
    {
        try {
            $records = $this->chartOfAccountService->toggleStatus($id);
            return JsonResponser::send(false, 'Record updated successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], Response::HTTP_CONFLICT);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function downloadTemplate()
    {
        try {
            return Excel::download(new ChartOfAccountDownloadTemplateExport(), 'chart_of_account_template.xlsx');
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], Response::HTTP_CONFLICT);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function bulkUpload(Request $request)
    {
        try {
            // Validate the file
            $request->validate([
                'file' => 'required|mimes:xlsx,csv|max:2048'
            ]);

            // Read file contents
            $data = Excel::toCollection(new ChartOfAccountImport(), $request->file('file'));

            // Ensure file is not empty
            if ($data->isEmpty() || $data[0]->isEmpty()) {
                throw new BadRequestException("Uploaded file is empty or contains no valid data.", Response::HTTP_BAD_REQUEST);
            }

            // Process data
            $response = $this->chartOfAccountService->processBulkUpload($data[0]);

            return JsonResponser::send(false, 'Record(s) uploaded successfully', $response, Response::HTTP_OK);
        } catch (ValidationException $e) {
            return JsonResponser::send(true, 'Validation Error', $e->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], Response::HTTP_CONFLICT);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
