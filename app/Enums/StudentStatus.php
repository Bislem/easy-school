<?php

namespace App\Enums;

enum StudentStatus: string
{
    case ACTIVE = 'active';
    case ENROLLED = 'enrolled';
    case WAITING = 'waiting';
    case STOPPED = 'stopped';
    case SUSPENDED = 'suspended';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
