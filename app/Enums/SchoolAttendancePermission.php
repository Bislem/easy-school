<?php

namespace App\Enums;

enum SchoolAttendancePermission: string
{
    case VIEW = 'school_attendance.view';
    case MANAGE = 'school_attendance.manage';
}
