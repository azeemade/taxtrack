<?php

namespace App\Enums;

/**
 * Enum SubscriptionRefundEnums
 * 
 * This enum defines defined all available options for SubscriptionRefundEnums.
 */
enum SubscriptionRefundEnums: string
{
    case FULL_REFUND = 'full-refund';
    case PARTIAL_REFUND = 'partial-refund';
}
