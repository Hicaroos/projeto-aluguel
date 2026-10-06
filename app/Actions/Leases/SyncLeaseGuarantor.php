<?php

namespace App\Actions\Leases;

use App\Models\Lease;

class SyncLeaseGuarantor
{
    /**
     * Save the lease guarantor, or remove it when the lease is no longer guaranteed by one.
     *
     * @param  array<string, mixed>|null  $attributes
     */
    public function handle(Lease $lease, ?array $attributes): void
    {
        if ($attributes === null) {
            $lease->guarantor()->delete();

            return;
        }

        $lease->guarantor()->updateOrCreate([], $attributes);
    }
}
