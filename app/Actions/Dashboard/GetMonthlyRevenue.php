<?php

namespace App\Actions\Dashboard;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Carbon\CarbonImmutable;

class GetMonthlyRevenue
{
    /**
     * Get the expected and received amounts of the account over the last months, oldest first.
     *
     * @return list<array{month: string, expected: float, received: float}>
     */
    public function handle(?int $accountId, CarbonImmutable $currentMonth, int $months = 6): array
    {
        $firstMonth = $currentMonth->startOfMonth()->subMonthsNoOverflow($months - 1);

        $paymentsByMonth = Payment::where('account_id', $accountId)
            ->whereHas('lease')
            ->where('status', '!=', PaymentStatus::Canceled)
            ->whereBetween('due_date', [$firstMonth->toDateString(), $currentMonth->endOfMonth()->toDateString()])
            ->withSum('receipts as received_amount', 'amount')
            ->get(['id', 'amount', 'due_date'])
            ->groupBy(fn (Payment $payment): string => $payment->due_date->format('Y-m'));

        return array_map(function (int $offset) use ($firstMonth, $paymentsByMonth): array {
            $month = $firstMonth->addMonthsNoOverflow($offset);
            $payments = $paymentsByMonth->get($month->format('Y-m'), collect());

            return [
                'month' => $month->toDateString(),
                'expected' => round($payments->sum(fn (Payment $payment): float => (float) $payment->amount), 2),
                'received' => round($payments->sum(fn (Payment $payment): float => (float) $payment->received_amount), 2),
            ];
        }, range(0, $months - 1));
    }
}
