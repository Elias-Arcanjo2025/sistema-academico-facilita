<?php

namespace App\Policies;

use App\Models\User;

class QueuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin_full');
    }

    public function delete(User $user): bool
    {
        return $user->hasRole('admin_full');
    }
}
