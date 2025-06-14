<?php

namespace App\Http\Controllers\v1\Company\Banking\PaymentMethods;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Banking\Banks\CreateBankConnectionRequest;
use App\Http\Requests\Company\Banking\Banks\CreateBankRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Responser\JsonResponser;
use App\Services\ChartOfAccount\ChartOfAccountService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BankAccountControllerRework extends Controller
{
    protected ChartOfAccountService $chartOfAccountService;

    public function __construct(ChartOfAccountService $chartOfAccountService)
    {
        $this->chartOfAccountService = $chartOfAccountService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->chartOfAccountService->allCashAndBankAccounts($request);
            if ($request->export) return $records;
            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateBankRequest $request)
    {
        try {
            DB::beginTransaction();
            $record = $this->chartOfAccountService->createAccount($request->validated());
            DB::commit();
            return JsonResponser::send(false, 'Bank created successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display the specified resource.
     */
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

    /**
     * Update the specified resource in storage.
     */
    public function update(CreateBankRequest $request, string $id)
    {
        try {
            DB::beginTransaction();
            $record = $this->chartOfAccountService->updateAccount($request, $id);
            DB::commit();
            return JsonResponser::send(false, 'Bank updated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
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

    /**
     * Remove the specified resource from storage.
     */
    public function delete($id)
    {
        try {
            $records = $this->chartOfAccountService->delete($id);
            return JsonResponser::send(false, 'Record deleted successfully', null, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], Response::HTTP_CONFLICT);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function bankTransactions(SharedFilterRequest $request)
    {
        try {
            $records = $this->bankAccountService->bankTransactions($request);

            if ($request->export) {
                return $this->bankAccountService->export($records, $request->export);
            }

            if ($request["paginate"]) {
                $stats = $this->bankAccountService->stats($request);
                $records = [
                    ...$stats,
                    'data' => $records
                ];
            }

            return JsonResponser::send(false, 'Record(s) found successfully', $records, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }


    /**
     * Display a listing of the resource.
     */
    public function accountTypes()
    {
        try {
            $record = $this->bankAccountService->bankAccountTypes();
            return JsonResponser::send(false, 'Record(s) found successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function initiateConnection()
    {
        try {
            $this->bankAccountService->initiateBankAccountConnection();
            return JsonResponser::send(false, 'OTP sent successfully', null, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    public function completeConnection(CreateBankConnectionRequest $request)
    {
        try {
            $record = $this->bankAccountService->completeBankAccountConnection($request->validated());
            return JsonResponser::send(false, 'Account connected successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }
}
