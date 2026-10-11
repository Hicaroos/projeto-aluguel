<?php

namespace App\Policies;

use App\Models\OwnerLedgerEntry;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class OwnerLedgerEntryPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can delete the entry. Only adjustments entered by hand may be
     * removed; the other entries follow what made them, e.g. a receipt or an expense.
     */
    public function delete(User $user, OwnerLedgerEntry $entry): Response
    {
        $ownership = $this->belongsToUserAccount($user, $entry);

        if ($ownership->denied()) {
            return $ownership;
        }

        return $entry->isAdjustment()
            ? Response::allow()
            : Response::deny(__('Só os ajustes lançados à mão podem ser excluídos.'));
    }
}
