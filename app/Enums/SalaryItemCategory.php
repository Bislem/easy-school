<?php

namespace App\Enums;

enum SalaryItemCategory: string
{
    case BASE_SALARY = 'BASE_SALARY';
    case PRIME = 'PRIME';
    case INDEMNITY = 'INDEMNITY';
    case OTHER_EARNING = 'OTHER_EARNING';
    case DEDUCTION = 'DEDUCTION';
}
