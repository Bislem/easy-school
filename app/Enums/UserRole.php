<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case TEACHER = 'teacher';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrateur',
            self::TEACHER => 'Enseignant',
        };
    }
}
