<?php

namespace App\Policies;

use App\Models\LeaseDocument;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class LeaseDocumentPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can open or download the document.
     */
    public function view(User $user, LeaseDocument $document): Response
    {
        return $this->belongsToUserAccount($user, $document);
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(User $user, LeaseDocument $document): Response
    {
        return $this->belongsToUserAccount($user, $document);
    }
}
