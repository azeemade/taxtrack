<?php

namespace App\Enums;

enum FinancialDocumentStatusEnums: string
{
    case DRAFT = 'draft';
    case CONVERTED_TO_INVOICE = 'converted-to-invoice';
    case ISSUED = 'issued';
    case ACTIVE = 'active';
    case OVERDUE = 'overdue';
    case VOID = 'void';
    case POSTED = 'posted';
}
