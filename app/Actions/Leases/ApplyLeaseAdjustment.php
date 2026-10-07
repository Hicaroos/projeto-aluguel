<?php

namespace App\Actions\Leases;

use App\Enums\PaymentStatus;
use App\Models\Lease;
use App\Models\LeaseAdjustment;
use Illuminate\Support\Facades\DB;
use LogicException;

class ApplyLeaseAdjustment
{
    /**
     * Adjust the rent from the lease's next anniversary on, either by an index percent or to a new amount
     * agreed between the parties (its percent is then worked out for the history): record it in the
     * history, update the lease amount and the rent payments from that month on that are still
     * pending without any receipt. Payments already (partly) paid keep their amount.
     *
     * @param  array{percent?: float|null, new_amount?: float|null}  $change
     */
    public function handle(Lease $lease, array $change, ?string $notes = null): LeaseAdjustment
    {
        $anniversary = $lease->nextAdjustmentAnniversary()
            ?? throw new LogicException('This lease has no anniversary left to adjust.');

        return DB::transaction(function () use ($lease, $change, $notes, $anniversary): LeaseAdjustment {
            $previousAmount = (float) $lease->amount;

            if (isset($change['new_amount'])) {
                $newAmount = round($change['new_amount'], 2);
                $percent = round(($newAmount / $previousAmount - 1) * 100, 2);
            } else {
                $percent = (float) ($change['percent'] ?? 0);
                $newAmount = round($previousAmount * (1 + $percent / 100), 2);
            }

            $adjustment = $lease->adjustments()->create([
                'effective_on' => $anniversary,
                'adjustment_index' => $lease->adjustment_index,
                'percent' => $percent,
                'previous_amount' => $previousAmount,
                'new_amount' => $newAmount,
                'notes' => $notes,
            ]);

            $lease->update(['amount' => $newAmount]);

            $lease->payments()
                ->rent()
                ->where('status', PaymentStatus::Pending)
                ->whereDoesntHave('receipts')
                ->whereDate('reference_month', '>=', $anniversary->startOfMonth())
                ->update(['amount' => $newAmount]);

            return $adjustment;
        });
    }
}
