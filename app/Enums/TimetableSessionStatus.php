<?php

namespace App\Enums;

enum TimetableSessionStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case CANCELLED = 'cancelled';
}
