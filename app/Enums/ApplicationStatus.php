<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case NEW = 'new';
    case CONTACTED = 'contacted';
    case WAITING = 'waiting';
    case APPROVED = 'approved';
    case REGISTERED = 'registered';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
}
