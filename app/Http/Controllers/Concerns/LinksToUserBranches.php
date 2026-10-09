<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Owner;
use App\Models\Tenant;
use App\Models\User;

trait LinksToUserBranches
{
    /**
     * Link a tenant or owner to the branch the user is looking at, or to every branch they work in.
     * Users who see every branch leave the person unlinked, so every branch sees them.
     */
    protected function linkToUserBranches(User $user, Tenant|Owner $person): void
    {
        $selectedId = $user->selectedBranchId();

        foreach ($selectedId !== null ? [$selectedId] : ($user->accessibleBranchIds() ?? []) as $branchId) {
            $person::linkToBranch([$person->id], $branchId);
        }
    }
}
