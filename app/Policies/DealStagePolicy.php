<?php

namespace App\Policies;

use App\Models\DealStage;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class DealStagePolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can update the deal stage.
     */
    public function update(User $user, DealStage $stage): Response
    {
        return $this->belongsToUserAccount($user, $stage);
    }

    /**
     * Determine whether the user can delete the deal stage.
     */
    public function delete(User $user, DealStage $stage): Response
    {
        return $this->belongsToUserAccount($user, $stage);
    }
}
