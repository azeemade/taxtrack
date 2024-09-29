<?php

namespace App\Enums;

enum GeneralEnums: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case SUSPENDED = 'suspended';
    case DECLINED = 'declined';
}
