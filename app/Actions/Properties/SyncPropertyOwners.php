<?php

namespace App\Actions\Properties;

use App\Models\Owner;
use App\Models\Property;

class SyncPropertyOwners
{
    /**
     * Record who owns the property: the co-owners with their shares, and the main owner with
     * whatever is left. Every owner is linked to the property's branch, so the branch lists them.
     *
     * @param  array<int, float>  $coOwnerShares  Shares keyed by owner id, the main owner excluded.
     */
    public function handle(Property $property, array $coOwnerShares): void
    {
        unset($coOwnerShares[$property->owner_id]);

        $shares = [$property->owner_id => round(100 - array_sum($coOwnerShares), 2)] + $coOwnerShares;

        $property->owners()->sync(array_map(fn (float $share): array => ['share_percent' => $share], $shares));

        Owner::linkToBranch(array_keys($shares), $property->branch_id);
    }
}
