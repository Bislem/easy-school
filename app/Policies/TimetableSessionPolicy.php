<?php

namespace App\Policies;

use App\Enums\TimetablePermission;
use App\Models\TimetableSession;
use App\Models\User;

class TimetableSessionPolicy
{
    public function viewAny(User $user): bool { return $user->can(TimetablePermission::VIEW->value); }
    public function view(User $user, TimetableSession $session): bool { return $user->can(TimetablePermission::VIEW->value); }
    public function create(User $user): bool { return $user->can(TimetablePermission::MANAGE->value); }
    public function update(User $user, TimetableSession $session): bool { return $user->can(TimetablePermission::MANAGE->value); }
    public function delete(User $user, TimetableSession $session): bool { return $user->can(TimetablePermission::MANAGE->value); }
}
