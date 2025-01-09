<?php

namespace App\Enums;

/**
 * Enum EmailTemplateModelEnums
 * 
 * This enum defines defined all available options for EmailTemplateModelEnums.
 */
enum EmailTemplateModelEnums: string
{
    case CREDIT_NOTE = 'Credit Note';
    case PURCHASE_ORDER = 'Purchase Order';
    case QUOTE = 'Quote';
    case SALES_INVOICE = 'Sales Invoice';
    case PURCHASE_INVOICE = 'Purchase Invoice';
    case RECURRING_INVOICE = 'Recurring Invoice';
    case DEBIT_NOTE = 'Debit Note';
    case RECEIPT = 'Receipt';
}
