<?php

namespace App\Enums;

enum SalaryCalculationType: string
{
    case FIXED_MONTHLY = 'FIXED_MONTHLY';
    case HOURLY = 'HOURLY';
    case DAILY = 'DAILY';
    case PER_SESSION = 'PER_SESSION';
}
