<?php

namespace App\Enums;

enum StudentAcademicResult: string
{
    case PASSED = 'passed';
    case FAILED = 'failed';
    case WITHDRAWN = 'withdrawn';
    case TRANSFERRED = 'transferred';
}
