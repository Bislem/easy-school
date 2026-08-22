<?php

namespace App\Enums;

enum StaffPermission: string
{
    case VIEW_ANY = 'staff.viewAny';
    case VIEW = 'staff.view';
    case CREATE = 'staff.create';
    case UPDATE = 'staff.update';
    case CHANGE_STATUS = 'staff.changeStatus';
    case MANAGE_TYPES = 'staff.manageTypes';
}
