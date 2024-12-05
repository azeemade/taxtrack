<?php

namespace App\Services\PaymentRecords;

use App\Enums\PaymentStatusEnums;
use App\Helpers\GeneralHelper;
use App\Models\PaymentRecord;
use App\Services\SharedServices\SharedActionService;

class PaymentRecordService
{
    protected SharedActionService $sharedActionServices;
    public function __construct(
        SharedActionService $sharedActionServices
    ) {
        $this->sharedActionServices = $sharedActionServices;
    }

    public function create($request)
    {
        $record = PaymentRecord::create($request);

        $amountDue = $record->recordable->amount_due;
        $record->recordable()->update([
            'payment_status' => $amountDue > 0 ? PaymentStatusEnums::PARTIAL_PAYMENT->value : PaymentStatusEnums::FULL_PAYMENT->value
        ]);

        return $record;
    }

    public function generatePaymentId()
    {
        return GeneralHelper::getModelUniqueOrderlyId([
            "modelNamespace" => 'App\Models\PaymentRecord',
            "modelField" => 'paymentID',
            "prefix" => 'pr-',
            "idLength" => 4,
        ]);
    }
}
