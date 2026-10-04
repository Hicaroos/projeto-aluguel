<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class PropertyPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can update the property.
     */
    public function update(User $user, Property $property): Response
    {
        return $this->belongsToUserAccount($user, $property);
    }

    /**
     * Determine whether the user can delete the property.
     */
    public function delete(User $user, Property $property): Response
    {
        return $this->belongsToUserAccount($user, $property);
    }
}
