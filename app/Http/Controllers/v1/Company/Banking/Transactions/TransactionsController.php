<?php

namespace App\Http\Controllers\v1\Company\Banking\Transactions;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Banking\Transaction\UpdatePaymentMethodTransactionRequest;
use App\Responser\JsonResponser;
use App\Services\Transaction\TransactionService;
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
