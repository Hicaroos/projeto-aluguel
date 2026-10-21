<?php

namespace App\Policies;

use App\Models\OwnerPayout;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class OwnerPayoutPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can open the proof of the transfer.
     */
    public function view(User $user, OwnerPayout $payout): Response
    {
        return $this->belongsToUserAccount($user, $payout);
    }

    /**
     * Determine whether the user can attach or remove the proof of the transfer.
     */
    public function update(User $user, OwnerPayout $payout): Response
    {
        return $this->belongsToUserAccount($user, $payout);
    }

    /**
     * Determine whether the user can delete the payout.
     */
    public function delete(User $user, OwnerPayout $payout): Response
    {
        return $this->belongsToUserAccount($user, $payout);
    }
}
