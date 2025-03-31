<?php

namespace App\Http\Controllers\v1\Company\Banking\PaymentMethods;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Banking\Banks\CreateBankConnectionRequest;
use App\Http\Requests\Company\Banking\Banks\CreateBankRequest;
use App\Http\Requests\Shared\SharedFilterRequest;
use App\Responser\JsonResponser;
use App\Services\BankAccount\BankAccountService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BankAccountController extends Controller
{

    protected BankAccountService $bankAccountService;

    public function __construct(BankAccountService $bankAccountService)
    {
        $this->bankAccountService = $bankAccountService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(SharedFilterRequest $request)
    {
        try {
            $records = $this->bankAccountService->bankList($request);
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
            $record = $this->bankAccountService->updateOrCreate($request->validated());
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
    public function show(int $id)
    {
        try {
            $record = $this->bankAccountService->viewBank($id);
            return JsonResponser::send(false, 'Record found successfully', $record, Response::HTTP_OK);
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
            $record = $this->bankAccountService->updateOrCreate([...$request->validated(), "id" => $id]);
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


    public function toggleStatus(int $id)
    {
        try {
            DB::beginTransaction();
            $record = $this->bankAccountService->toggleStatus($id);
            DB::commit();
            return JsonResponser::send(false, 'Bank status updated successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
            return JsonResponser::send(true, 'Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        try {
            DB::beginTransaction();
            $record = $this->bankAccountService->delete($id);
            DB::commit();
            return JsonResponser::send(false, 'Bank deleted successfully', $record, Response::HTTP_OK);
        } catch (BadRequestException $e) {
            DB::rollBack();
            return JsonResponser::send(true, $e->getMessage(), [], $e->getCode());
        } catch (\Throwable $th) {
            DB::rollBack();
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
