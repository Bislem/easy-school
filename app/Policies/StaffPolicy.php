<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.view');
    }

    public function view(User $user, Staff $staff): bool
    {
        return $user->hasPermission('employees.view') || ($user->staff?->is($staff) ?? false);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.create');
    }

    public function update(User $user, Staff $staff): bool
    {
        return $user->hasPermission('employees.update');
    }

    public function changeStatus(User $user, Staff $staff): bool
    {
        return $user->hasPermission('employees.update');
    }
}
