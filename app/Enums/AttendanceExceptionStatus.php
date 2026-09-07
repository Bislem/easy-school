<?php

namespace App\Enums;

enum AttendanceExceptionStatus: string
{
    case ABSENT = 'ABSENT';
    case LATE = 'LATE';
    case EXCUSED = 'EXCUSED';
    case LEFT_EARLY = 'LEFT_EARLY';
}
