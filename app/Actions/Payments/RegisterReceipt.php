<?php

namespace App\Actions\Payments;

use App\Actions\Payouts\PostReceiptToLedger;
use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;

class RegisterReceipt
{
    public function __construct(private PostReceiptToLedger $postReceiptToLedger) {}

    /**
     * Register a received amount for the payment, recalculate its status and post what the owners
     * are due to their running accounts.
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

            $this->postReceiptToLedger->handle($receipt);

            return $receipt;
        });
    }
}
