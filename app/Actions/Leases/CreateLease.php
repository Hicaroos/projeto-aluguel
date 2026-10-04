<?php

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use Illuminate\Support\Facades\DB;

class CreateLease
{
    public function __construct(private SyncLeasePayments $syncLeasePayments) {}

    /**
     * Create an active lease, mark its property as rented and generate its upcoming payments.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(int $accountId, array $attributes): Lease
    {
        return DB::transaction(function () use ($accountId, $attributes): Lease {
            $lease = Lease::create([
                ...$attributes,
                'account_id' => $accountId,
                'status' => LeaseStatus::Active,
            ]);

            $lease->property->update(['status' => PropertyStatus::Rented]);

            $this->syncLeasePayments->handle($lease);

            return $lease;
        });
    }
}
