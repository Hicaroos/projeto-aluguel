<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class SalePolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can update the sale.
     */
    public function update(User $user, Sale $sale): Response
    {
        return $this->belongsToUserAccount($user, $sale);
    }

    /**
     * Determine whether the user can delete the sale.
     */
    public function delete(User $user, Sale $sale): Response
    {
        return $this->belongsToUserAccount($user, $sale);
    }
}
