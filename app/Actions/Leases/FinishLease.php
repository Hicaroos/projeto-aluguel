<?php

namespace App\Actions\Leases;

use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use Illuminate\Support\Facades\DB;

class FinishLease
{
    public function __construct(private SyncLeasePayments $syncLeasePayments) {}

    /**
     * End or terminate the lease, freeing its property and cancelling its upcoming payments.
     */
    public function handle(Lease $lease, LeaseStatus $status): void
    {
        DB::transaction(function () use ($lease, $status): void {
            $lease->update(['status' => $status]);
            $lease->property->update(['status' => PropertyStatus::Available]);

            $this->syncLeasePayments->cancelUpcoming($lease);
        });
    }
}
