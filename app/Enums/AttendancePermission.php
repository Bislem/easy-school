<?php
namespace App\Enums;
enum AttendancePermission:string
{
    case VIEW='attendance.view';
    case MANAGE_STUDENTS='attendance.students.manage';
    case MANAGE_TEACHERS='attendance.teachers.manage';
    case MANAGE_EMPLOYEES='attendance.employees.manage';
    case VALIDATE='attendance.validate';
    case CORRECT_LOCKED='attendance.correct_locked';
    case REPORTS='attendance.reports.view';
}
