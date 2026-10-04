<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\ChecksAccountOwnership;
use Illuminate\Auth\Access\Response;

class PaymentPolicy
{
    use ChecksAccountOwnership;

    /**
     * Determine whether the user can register a receipt: only open payments can receive amounts.
     */
    public function registerReceipt(User $user, Payment $payment): Response
    {
        $ownership = $this->belongsToUserAccount($user, $payment);

        if ($ownership->denied()) {
            return $ownership;
        }

        return $payment->isOpen()
            ? Response::allow()
            : Response::deny(__('Esta cobrança não está em aberto.'));
    }
}
