<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class LeasePolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can view the lease, e.g. to generate its contract.
     */
    public function view(User $user, Lease $lease): Response
    {
        return $this->belongsToUserAccount($user, $lease);
    }

    /**
     * Determine whether the user can update the lease: only active leases can change.
     */
    public function update(User $user, Lease $lease): Response
    {
        $ownership = $this->belongsToUserAccount($user, $lease);

        if ($ownership->denied()) {
            return $ownership;
        }

        return $lease->isActive()
            ? Response::allow()
            : Response::deny(__('Apenas contratos ativos podem ser alterados.'));
    }

    /**
     * Determine whether the user can end or terminate the lease.
     */
    public function finish(User $user, Lease $lease): Response
    {
        return $this->update($user, $lease);
    }

    /**
     * Determine whether the user can delete the lease.
     */
    public function delete(User $user, Lease $lease): Response
    {
        return $this->belongsToUserAccount($user, $lease);
    }
}
