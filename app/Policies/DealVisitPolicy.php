<?php

namespace App\Policies;

use App\Models\DealVisit;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class DealVisitPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can update the visit.
     */
    public function update(User $user, DealVisit $visit): Response
    {
        return $this->belongsToUserAccount($user, $visit);
    }

    /**
     * Determine whether the user can delete the visit.
     */
    public function delete(User $user, DealVisit $visit): Response
    {
        return $this->belongsToUserAccount($user, $visit);
    }
}
