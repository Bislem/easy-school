<?php

namespace App\Enums;

enum DataScope: string
{
    case OWN = 'own';
    case ASSIGNED = 'assigned';
    case SITE = 'site';
    case TENANT = 'tenant';

    public function rank(): int
    {
        return match ($this) {
            self::OWN => 0,
            self::ASSIGNED => 1,
            self::SITE => 2,
            self::TENANT => 3,
        };
    }
}
