<?php

namespace App\Enums;

enum PrivateSchoolInscriptionStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case WAITING_LIST = 'waiting_list';
    case CANCELLED = 'cancelled';
}
