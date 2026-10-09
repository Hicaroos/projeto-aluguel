<?php

namespace App\Policies;

use App\Models\Owner;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class OwnerPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can update the owner.
     */
    public function update(User $user, Owner $owner): Response
    {
        return $this->belongsToUserAccount($user, $owner);
    }

    /**
     * Determine whether the user can delete the owner.
     */
    public function delete(User $user, Owner $owner): Response
    {
        return $this->belongsToUserAccount($user, $owner);
    }
}
