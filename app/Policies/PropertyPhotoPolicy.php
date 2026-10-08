<?php

namespace App\Policies;

use App\Models\PropertyPhoto;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class PropertyPhotoPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can see the photo.
     */
    public function view(User $user, PropertyPhoto $photo): Response
    {
        return $this->belongsToUserAccount($user, $photo);
    }

    /**
     * Determine whether the user can change the photo, e.g. make it the cover.
     */
    public function update(User $user, PropertyPhoto $photo): Response
    {
        return $this->belongsToUserAccount($user, $photo);
    }

    /**
     * Determine whether the user can delete the photo.
     */
    public function delete(User $user, PropertyPhoto $photo): Response
    {
        return $this->belongsToUserAccount($user, $photo);
    }
}
