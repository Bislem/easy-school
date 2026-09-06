<?php

namespace App\Enums;

enum TimetablePermission: string
{
    case VIEW = 'timetable.view';
    case MANAGE = 'timetable.manage';
}
