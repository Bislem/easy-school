<?php

namespace App\Enums;

enum RoomType: string
{
    case CLASSROOM = 'classroom';
    case LABORATORY = 'laboratory';
    case COMPUTER_ROOM = 'computer_room';
    case SPORTS_ROOM = 'sports_room';
    case WORKSHOP = 'workshop';
    case AUDITORIUM = 'auditorium';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CLASSROOM => 'Salle de classe',
            self::LABORATORY => 'Laboratoire',
            self::COMPUTER_ROOM => 'Salle informatique',
            self::SPORTS_ROOM => 'Salle de sport',
            self::WORKSHOP => 'Atelier',
            self::AUDITORIUM => 'Auditorium',
            self::OTHER => 'Autre',
        };
    }
}
