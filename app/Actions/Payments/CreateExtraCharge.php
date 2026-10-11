<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Lease;
use App\Models\Payment;

class CreateExtraCharge
{
    /**
     * Charge the tenant of the lease an extra amount, such as a repair or a fine.
     *
     * Works for finished leases too, so debts left by a former tenant can be tracked.
     *
     * A null `pass_to_owner` follows the payout rules when the charge is paid.
     *
     * @param  array{description: string, amount: mixed, due_date: mixed, pass_to_owner?: bool|null}  $attributes
     */
    public function handle(Lease $lease, array $attributes): Payment
    {
        return $lease->payments()->create([
            'account_id' => $lease->account_id,
            'type' => PaymentType::Extra,
            'description' => $attributes['description'],
            'pass_to_owner' => $attributes['pass_to_owner'] ?? null,
            'reference_month' => null,
            'due_date' => $attributes['due_date'],
            'amount' => $attributes['amount'],
            'status' => PaymentStatus::Pending,
        ]);
    }
}
