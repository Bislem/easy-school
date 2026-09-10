<?php

namespace App\Enums;

enum SchoolDocumentType: string
{
    case SCHOOL_CERTIFICATE = 'school_certificate';
    case GROUP_TIMETABLE = 'group_timetable';
    case TEACHER_TIMETABLE = 'teacher_timetable';

    public function label(): string
    {
        return match ($this) {
            self::SCHOOL_CERTIFICATE => 'Certificat de scolarité',
            self::GROUP_TIMETABLE => 'Emploi du temps par groupe',
            self::TEACHER_TIMETABLE => 'Emploi du temps par enseignant',
        };
    }
}
