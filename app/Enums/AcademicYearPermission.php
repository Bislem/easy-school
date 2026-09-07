<?php

namespace App\Enums;

enum AcademicYearPermission: string
{
    case VIEW = 'academic_year.view';
    case CREATE = 'academic_year.create';
    case UPDATE = 'academic_year.update';
    case ACTIVATE = 'academic_year.activate';
    case CLOSE = 'academic_year.close';
    case ARCHIVE = 'academic_year.archive';
}
