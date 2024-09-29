<?php

namespace App\Enums;

enum CompanyStatusEnums: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case DECLINED = 'declined';
    case SUSPENDED = 'suspended';
}
