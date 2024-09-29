<?php

namespace App\Enums;

enum TransactionEntryEnums: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';
}
