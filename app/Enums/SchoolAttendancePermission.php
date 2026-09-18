<?php

namespace App\Enums;

enum SchoolAttendancePermission: string
{
    case VIEW = 'absences.view';
    case CREATE = 'absences.create';
    case UPDATE = 'absences.update';
    case DELETE = 'absences.delete';
    case MANAGE_STUDENTS = 'student_absences.manage';
    case MANAGE_TEACHERS = 'teacher_absences.manage';
    case MANAGE_JUSTIFICATIONS = 'absence_justifications.manage';
}
