<?php

namespace App\Enums;

enum AcademicYearStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case CLOSED = 'closed';
    case ARCHIVED = 'archived';
}
