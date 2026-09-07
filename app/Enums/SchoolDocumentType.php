<?php

namespace App\Enums;

enum SchoolDocumentType: string
{
    case SCHOOL_CERTIFICATE = 'school_certificate';

    public function label(): string
    {
        return match ($this) {
            self::SCHOOL_CERTIFICATE => 'Certificat de scolarité',
        };
    }
}
