<?php

namespace App\Actions\Leases;

use App\Enums\PropertyStatus;
use App\Models\Lease;
use Illuminate\Support\Facades\DB;

class UpdateLease
{
    public function __construct(
        private SyncLeasePayments $syncLeasePayments,
        private SyncLeaseGuarantor $syncLeaseGuarantor,
    ) {}

    /**
     * Update the lease terms and guarantor, moving the rented status when the property changes and
     * keeping the upcoming payments in line with the new terms.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $guarantor
     */
    public function handle(Lease $lease, array $attributes, ?array $guarantor = null): Lease
    {
        return DB::transaction(function () use ($lease, $attributes, $guarantor): Lease {
            $previousProperty = $lease->property;

            $lease->update([
                ...$attributes,
                'deposit_amount' => $attributes['deposit_amount'] ?? null,
                'surety_insurer' => $attributes['surety_insurer'] ?? null,
                'surety_policy_number' => $attributes['surety_policy_number'] ?? null,
            ]);

            $this->syncLeaseGuarantor->handle($lease, $guarantor);

            if ($previousProperty->id !== $lease->property_id) {
                $previousProperty->update(['status' => PropertyStatus::Available]);
                $lease->load('property')->property->update(['status' => PropertyStatus::Rented]);
            }

            $this->syncLeasePayments->handle($lease);

            return $lease;
        });
    }
}
