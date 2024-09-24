<?php

namespace App\Enums;

enum RecurringPeriodEnums: string
{
    case MONTH = 'month';
    case DAY = 'day';
    case WEEK = 'week';
    case YEAR = 'year';
}
