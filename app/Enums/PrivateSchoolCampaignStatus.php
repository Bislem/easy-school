<?php

namespace App\Enums;

enum PrivateSchoolCampaignStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case CLOSED = 'closed';
}
