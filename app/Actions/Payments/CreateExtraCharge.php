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
     * @param  array{description: string, amount: mixed, due_date: mixed}  $attributes
     */
    public function handle(Lease $lease, array $attributes): Payment
    {
        return $lease->payments()->create([
            'account_id' => $lease->account_id,
            'type' => PaymentType::Extra,
            'description' => $attributes['description'],
            'reference_month' => null,
            'due_date' => $attributes['due_date'],
            'amount' => $attributes['amount'],
            'status' => PaymentStatus::Pending,
        ]);
    }
}
