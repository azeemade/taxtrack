<?php

namespace App\Http\Controllers\v1\Company\Banking\Transactions;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Banking\Transaction\CreateTransactionRequest;
use App\Http\Requests\Company\Banking\Transaction\UpdatePaymentMethodTransactionRequest;
use App\Responser\JsonResponser;
use App\Services\Transaction\TransactionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TransactionsController extends Controller
{
    protected TransactionService $transactionService;
    public function __construct(
        TransactionService $transactionService
    ) {
        $this->transactionService = $transactionService;
    }


    public function transactionOverview(Request $request)
    {
        try {
            $data = $this->transactionService->getTransactionOverview($request);
            return JsonResponser::send(false, 'Transaction overview fetched!', $data);
        } catch (Exception $e) {
            return JsonResponser::send(true, $e->getMessage(), [], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Error: ' . $th->getMessage(), [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function transactionList(Request $request)
    {
        try {
            $data = $this->transactionService->getTransactionList($request);
            return JsonResponser::send(false, 'Transaction list fetched!', $data);
        } catch (Exception $e) {
            return JsonResponser::send(true, $e->getMessage(), [], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Error: ' . $th->getMessage(), [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function createFinanceTransaction(CreateTransactionRequest $request)
    {
        try {
            $record = $this->transactionService->createFinanceTransaction($request);
            return JsonResponser::send(false, 'Transaction created successfully!', $record, Response::HTTP_CREATED);
        } catch (Exception $e) {
            return JsonResponser::send(true, $e->getMessage(), [], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $th) {
            return JsonResponser::send(true, 'Internal Server Error: ' . $th->getMessage(), [], Response::HTTP_INTERNAL_SERVER_ERROR, $th);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function updatePaymentMethodTransaction(UpdatePaymentMethodTransactionRequest $request, string $id)
    {
        try {
            DB::beginTransaction();
            $record = $this->transactionService->updatePaymentMethodTransaction([...$request->validated(), "id" => $id]);
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
}
