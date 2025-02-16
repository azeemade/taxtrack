<?php

namespace App\Services\Transaction;

use App\Exceptions\BadRequestException;
use App\Models\PaymentRecord;
use Illuminate\Http\Response;

class TransactionService
{
    public function updatePaymentMethodTransaction($request)
    {
        $record = PaymentRecord::find($request['id']);
        if (!$record) {
            throw new BadRequestException("Record not found", Response::HTTP_NOT_FOUND);
        }

        $record->update($request);

        return $record;
    }
}
