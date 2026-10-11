<?php

namespace App\Policies;

use App\Models\DealProposal;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class DealProposalPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can update the proposal.
     */
    public function update(User $user, DealProposal $proposal): Response
    {
        return $this->belongsToUserAccount($user, $proposal);
    }

    /**
     * Determine whether the user can delete the proposal.
     */
    public function delete(User $user, DealProposal $proposal): Response
    {
        return $this->belongsToUserAccount($user, $proposal);
    }
}
