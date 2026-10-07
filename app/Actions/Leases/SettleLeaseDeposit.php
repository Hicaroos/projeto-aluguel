<?php

namespace App\Actions\Leases;

use App\Actions\Payments\CreateExtraCharge;
use App\Actions\Payments\RegisterReceipt;
use App\Enums\PaymentMethod;
use App\Models\Lease;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class SettleLeaseDeposit
{
    public function __construct(
        private CreateExtraCharge $createExtraCharge,
        private RegisterReceipt $registerReceipt,
    ) {}

    /**
     * Settle the deposit of a finished lease: turn the exit deductions (e.g. painting, cleaning) into
     * extra charges, pay the chosen open payments and those deductions out of the deposit, oldest due
     * first, and record what was left and given back to the tenant.
     *
     * When the debts exceed the deposit, it is used up and the rest stays open as the tenant's debt.
     *
     * @param  list<int>  $paymentIds  Open payments of the lease to pay out of the deposit.
     * @param  list<array{description: string, amount: float|string}>  $deductions
     */
    public function handle(Lease $lease, array $paymentIds, array $deductions, CarbonInterface $settledOn): void
    {
        DB::transaction(function () use ($lease, $paymentIds, $deductions, $settledOn): void {
            $charges = array_map(fn (array $deduction): Payment => $this->createExtraCharge->handle($lease, [
                'description' => $deduction['description'],
                'amount' => $deduction['amount'],
                'due_date' => $settledOn->toDateString(),
            ]), $deductions);

            $toPay = $lease->openPayments()
                ->whereKey([...$paymentIds, ...array_map(fn (Payment $charge): int => $charge->id, $charges)])
                ->get();

            $available = round((float) $lease->deposit_amount, 2);

            foreach ($toPay as $payment) {
                $amount = min($available, $payment->remainingAmount());

                if ($amount <= 0) {
                    break;
                }

                $this->registerReceipt->handle($payment, [
                    'amount' => $amount,
                    'date' => $settledOn->toDateString(),
                    'payment_method' => PaymentMethod::Deposit,
                    'notes' => __('Abatido da caução'),
                ]);

                $available = round($available - $amount, 2);
            }

            $lease->update([
                'deposit_settled_on' => $settledOn->toDateString(),
                'deposit_refunded_amount' => $available,
            ]);
        });
    }
}
