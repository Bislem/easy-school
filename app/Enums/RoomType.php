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
}
