<?php

namespace App\Actions\Payments;

use App\Actions\Payouts\PostReceiptToLedger;
use App\Models\Payment;
use App\Models\Receipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterReceipt
{
    public function __construct(private PostReceiptToLedger $postReceiptToLedger) {}

    /**
     * Register a received amount for the payment, recalculate its status and post what the owners
     * are due to their running accounts. The payment stays locked while it is checked and paid, so
     * two receipts sent at once (a double click) can't together pay more than it owes.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException When the amount is more than what is still owed.
     */
    public function handle(Payment $payment, array $attributes): Receipt
    {
        return DB::transaction(function () use ($payment, $attributes): Receipt {
            Payment::withoutGlobalScopes()->whereKey($payment->id)->lockForUpdate()->first();

            if (round((float) $attributes['amount'], 2) > $payment->remainingAmount()) {
                throw ValidationException::withMessages([
                    'amount' => __('O valor não pode ser maior que o saldo em aberto da cobrança.'),
                ]);
            }

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
