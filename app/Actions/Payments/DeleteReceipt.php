<?php

namespace App\Actions\Payments;

use App\Models\Receipt;
use Illuminate\Support\Facades\DB;

class DeleteReceipt
{
    /**
     * Delete the receipt and recalculate the status of its payment.
     */
    public function handle(Receipt $receipt): void
    {
        DB::transaction(function () use ($receipt): void {
            $receipt->delete();

            $receipt->payment->refreshStatus();
        });
    }
}
