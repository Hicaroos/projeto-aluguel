<?php

namespace App\Policies;

use App\Models\ExpenseDocument;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class ExpenseDocumentPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can open or download the document.
     */
    public function view(User $user, ExpenseDocument $document): Response
    {
        return $this->belongsToUserAccount($user, $document);
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(User $user, ExpenseDocument $document): Response
    {
        return $this->belongsToUserAccount($user, $document);
    }
}
