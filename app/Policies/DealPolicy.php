<?php

namespace App\Policies;

use App\Models\Deal;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class DealPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can update the deal.
     */
    public function update(User $user, Deal $deal): Response
    {
        return $this->belongsToUserAccount($user, $deal);
    }

    /**
     * Determine whether the user can delete the deal.
     */
    public function delete(User $user, Deal $deal): Response
    {
        return $this->belongsToUserAccount($user, $deal);
    }
}
