<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class LeadPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can update the lead.
     */
    public function update(User $user, Lead $lead): Response
    {
        return $this->belongsToUserAccount($user, $lead);
    }

    /**
     * Determine whether the user can delete the lead.
     */
    public function delete(User $user, Lead $lead): Response
    {
        return $this->belongsToUserAccount($user, $lead);
    }
}
