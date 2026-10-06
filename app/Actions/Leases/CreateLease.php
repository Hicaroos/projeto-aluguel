<?php

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use Illuminate\Support\Facades\DB;

class CreateLease
{
    public function __construct(
        private SyncLeasePayments $syncLeasePayments,
        private SyncLeaseGuarantor $syncLeaseGuarantor,
    ) {}

    /**
     * Create an active lease with its guarantor, mark its property as rented and generate its upcoming payments.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $guarantor
     */
    public function handle(int $accountId, array $attributes, ?array $guarantor = null): Lease
    {
        return DB::transaction(function () use ($accountId, $attributes, $guarantor): Lease {
            $lease = Lease::create([
                ...$attributes,
                'account_id' => $accountId,
                'status' => LeaseStatus::Active,
            ]);

            $this->syncLeaseGuarantor->handle($lease, $guarantor);

            $lease->property->update(['status' => PropertyStatus::Rented]);

            $this->syncLeasePayments->handle($lease);

            return $lease;
        });
    }
}
