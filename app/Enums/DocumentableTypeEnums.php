<?php

namespace App\Enums;

/**
 * Enum DocumentableTypeEnums
 * 
 * This enum defines defined all available options for DocumentableTypeEnums.
 */
enum DocumentableTypeEnums: string
{
    case INVOICE = 'invoice';
    case QUOTE = 'quote';
    case PURCHASE_ORDER = 'purchase_order';
    case PURCHASE_INVOICE = 'purchase_invoice';
}
