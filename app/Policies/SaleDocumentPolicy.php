<?php

namespace App\Policies;

use App\Models\SaleDocument;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class SaleDocumentPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can open or download the document.
     */
    public function view(User $user, SaleDocument $document): Response
    {
        return $this->belongsToUserAccount($user, $document);
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(User $user, SaleDocument $document): Response
    {
        return $this->belongsToUserAccount($user, $document);
    }
}
