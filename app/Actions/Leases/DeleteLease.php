<?php

namespace App\Actions\Leases;

use App\Enums\PropertyStatus;
use App\Models\Lease;
use Illuminate\Support\Facades\DB;

class DeleteLease
{
    public function __construct(private SyncLeasePayments $syncLeasePayments) {}

    /**
     * Delete the lease, freeing its property and cancelling its upcoming payments when it was active.
     */
    public function handle(Lease $lease): void
    {
        DB::transaction(function () use ($lease): void {
            if ($lease->isActive()) {
                $lease->property->update(['status' => PropertyStatus::Available]);

                $this->syncLeasePayments->cancelUpcoming($lease);
            }

            $lease->delete();
        });
    }
}
