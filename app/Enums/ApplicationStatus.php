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
    case COMPLETED = 'completed';

    /** Application statuses an applicant can still move between before registration. */
    public static function applicationStages(): array
    {
        return [self::NEW, self::CONTACTED, self::WAITING, self::APPROVED, self::REJECTED, self::CANCELLED];
    }
}
