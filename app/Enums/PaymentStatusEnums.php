<?php

namespace App\Enums;

enum PaymentStatusEnums: string
{
    case PENDING = 'pending';
    case PARTIAL_PAYMENT = 'partial-payment';
    case FULL_PAYMENT = 'full-payment';
}
