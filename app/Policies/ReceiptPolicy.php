<?php

namespace App\Policies;

use App\Models\Receipt;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class ReceiptPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can view the receipt.
     */
    public function view(User $user, Receipt $receipt): Response
    {
        return $this->belongsToUserAccount($user, $receipt);
    }

    /**
     * Determine whether the user can delete the receipt.
     */
    public function delete(User $user, Receipt $receipt): Response
    {
        return $this->belongsToUserAccount($user, $receipt);
    }
}
