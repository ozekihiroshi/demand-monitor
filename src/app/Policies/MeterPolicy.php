<?php
namespace App\Policies;

use App\Models\Meter;
use App\Models\User;

class MeterPolicy
{
    public function view(User $user, Meter $meter): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if (! $user->can('meters.view')) {
            return false;
        }

        return $user->groups()->whereKey($meter->group_id)->exists();
    }

    public function update(User $user, Meter $meter): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if (! $user->can('meters.manage')) {
            return false;
        }

        return $user->groups()->whereKey($meter->group_id)->exists();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin') || $user->can('meters.view');
    }

}
