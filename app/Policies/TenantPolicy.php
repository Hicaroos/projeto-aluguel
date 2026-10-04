<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class TenantPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can update the tenant.
     */
    public function update(User $user, Tenant $tenant): Response
    {
        return $this->belongsToUserAccount($user, $tenant);
    }

    /**
     * Determine whether the user can delete the tenant.
     */
    public function delete(User $user, Tenant $tenant): Response
    {
        return $this->belongsToUserAccount($user, $tenant);
    }
}
