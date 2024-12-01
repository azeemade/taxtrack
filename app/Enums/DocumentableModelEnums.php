<?php

namespace App\Enums;

/**
 * Enum DocumentableModelEnums
 * 
 * This enum defines defined all available options for DocumentableTypeEnums.
 */
enum DocumentableModelEnums: string
{
    case INVOICE = 'App\Models\Invoice';
    case QUOTE = 'App\Models\Quote';
    case PURCHASE_ORDER = 'App\Models\PurchaseOrder';
    case PURCHASE_INVOICE = 'App\Models\PurchaseInvoice';
}
