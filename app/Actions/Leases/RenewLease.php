<?php

namespace App\Actions\Leases;

use App\Models\Lease;
use App\Models\LeaseRenewal;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RenewLease
{
    public function __construct(private SyncLeasePayments $syncLeasePayments) {}

    /**
     * Extend the lease term, keeping its rent: changing the rent is up to the annual adjustment,
     * which becomes available when the renewal brings the next anniversary into the term.
     *
     * The payments of the new months are generated as usual, including the ones missed when
     * the lease was renewed after it had already ended.
     */
    public function handle(Lease $lease, CarbonInterface $newEndDate, ?string $notes = null): LeaseRenewal
    {
        return DB::transaction(function () use ($lease, $newEndDate, $notes): LeaseRenewal {
            $renewal = $lease->renewals()->create([
                'previous_end_date' => $lease->end_date,
                'new_end_date' => $newEndDate->toDateString(),
                'notes' => $notes,
            ]);

            $lease->update(['end_date' => $newEndDate->toDateString()]);

            $this->syncLeasePayments->handle($lease);

            return $renewal;
        });
    }
}
