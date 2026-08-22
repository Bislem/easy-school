<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case TEACHER = 'teacher';
    case EMPLOYEE = 'employee';
    case STUDENT = 'student';
    case PARENT = 'parent';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrateur',
            self::TEACHER => 'Enseignant',
            self::EMPLOYEE => 'Employé',
            self::STUDENT => 'Étudiant',
            self::PARENT => 'Parent',
        };
    }
}
