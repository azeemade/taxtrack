<?php

namespace App\Enums;

enum CustomerTypeEnums: string
{
    case BUSINESS = 'business';
    case INDIVIDUAL = 'individual';
    case ORGANIZATION = 'organization';
    case PROPRIETORSHIP = 'proprietorship';
    case PARTNERSHIP = 'partnership';
    case CORPORATION = 'corporation';
    case ACCOUNTANT = 'accountant';
}
