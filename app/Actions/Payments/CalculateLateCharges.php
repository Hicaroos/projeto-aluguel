<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use Carbon\CarbonInterface;

class CalculateLateCharges
{
    /**
     * Calculate the late fee and interest the lease contract charges on the rent amount paid on the given date.
     *
     * The fee is charged once from the first day late; interest is simple and pro rata, counting each day
     * as 1/30 of the monthly rate. Extra charges carry no late charges.
     *
     * @return array{days_late: int, late_fee: float, interest: float}
     */
    public function handle(Payment $payment, float $amount, CarbonInterface $paidOn): array
    {
        $daysLate = max(0, (int) $payment->due_date->startOfDay()->diffInDays($paidOn->copy()->startOfDay(), false));

        if ($payment->isExtra() || $daysLate === 0 || $amount <= 0) {
            return ['days_late' => $daysLate, 'late_fee' => 0.0, 'interest' => 0.0];
        }

        $lease = $payment->lease;

        return [
            'days_late' => $daysLate,
            'late_fee' => round($amount * (float) $lease->late_fee_percent / 100, 2),
            'interest' => round($amount * (float) $lease->monthly_interest_percent / 100 / 30 * $daysLate, 2),
        ];
    }
}
