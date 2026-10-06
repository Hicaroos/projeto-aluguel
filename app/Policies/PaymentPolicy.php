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

    /**
     * Determine whether the user can delete the payment: only extra charges with no receipts.
     *
     * Rent payments follow the lease terms, and received amounts must be removed first so no
     * money history is lost silently.
     */
    public function delete(User $user, Payment $payment): Response
    {
        $ownership = $this->belongsToUserAccount($user, $payment);

        if ($ownership->denied()) {
            return $ownership;
        }

        if (! $payment->isExtra()) {
            return Response::deny(__('Cobranças de aluguel são controladas pelo contrato e não podem ser excluídas.'));
        }

        return $payment->receipts()->exists()
            ? Response::deny(__('Remova os pagamentos registrados antes de excluir esta cobrança.'))
            : Response::allow();
    }
}
