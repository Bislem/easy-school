<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

class StaffPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role->value === 'admin' ? true : null;
    }

    public function viewAny(User $user): bool { return false; }
    public function view(User $user, Staff $staff): bool { return $user->staff?->is($staff) ?? false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, Staff $staff): bool { return false; }
    public function changeStatus(User $user, Staff $staff): bool { return false; }
}
