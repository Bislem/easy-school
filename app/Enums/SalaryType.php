<?php

namespace App\Enums;

enum SalaryType: string
{
    case MONTHLY = 'monthly';
    case HOURLY = 'hourly';
    case PER_SESSION = 'per_session';
    case DAILY = 'daily';
    case CUSTOM = 'custom';
}
