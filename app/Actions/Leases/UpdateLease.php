<?php

namespace App\Actions\Leases;

use App\Enums\PropertyStatus;
use App\Models\Lease;
use Illuminate\Support\Facades\DB;

class UpdateLease
{
    public function __construct(private SyncLeasePayments $syncLeasePayments) {}

    /**
     * Update the lease terms, moving the rented status when the property changes and
     * keeping the upcoming payments in line with the new terms.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Lease $lease, array $attributes): Lease
    {
        return DB::transaction(function () use ($lease, $attributes): Lease {
            $previousProperty = $lease->property;

            $lease->update([
                ...$attributes,
                'deposit_amount' => $attributes['deposit_amount'] ?? null,
            ]);

            if ($previousProperty->id !== $lease->property_id) {
                $previousProperty->update(['status' => PropertyStatus::Available]);
                $lease->load('property')->property->update(['status' => PropertyStatus::Rented]);
            }

            $this->syncLeasePayments->handle($lease);

            return $lease;
        });
    }
}
