<?php

namespace App\Enums;

enum StudentAcademicEnrollmentStatus: string
{
    case PENDING = 'pending';
    case ENROLLED = 'enrolled';
    case TRANSFERRED = 'transferred';
    case WITHDRAWN = 'withdrawn';
    case COMPLETED = 'completed';
}
