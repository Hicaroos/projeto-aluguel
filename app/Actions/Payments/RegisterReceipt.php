<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;

class RegisterReceipt
{
    /**
     * Register a received amount for the payment and recalculate its status.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Payment $payment, array $attributes): Receipt
    {
        return DB::transaction(function () use ($payment, $attributes): Receipt {
            $receipt = $payment->receipts()->create([
                ...$attributes,
                'account_id' => $payment->account_id,
            ]);

            $payment->refreshStatus();

            return $receipt;
        });
    }
}
