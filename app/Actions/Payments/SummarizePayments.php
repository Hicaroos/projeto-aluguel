<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

class SummarizePayments
{
    /**
     * Summarize the expected, received, open and overdue amounts of the given payments.
     *
     * Canceled payments are left out of every figure.
     *
     * @param  Builder<Payment>  $query
     * @return array{expected: float, received: float, open: float, overdue: float, overdue_count: int}
     */
    public function handle(Builder $query): array
    {
        $payments = $query
            ->where('status', '!=', PaymentStatus::Canceled)
            ->withSum('receipts as received_amount', 'amount')
            ->get(['id', 'amount', 'status', 'due_date']);

        $outstanding = fn (Payment $payment): float => max(0, (float) $payment->amount - (float) $payment->received_amount);

        $overduePayments = $payments->filter(
            fn (Payment $payment): bool => $payment->status !== PaymentStatus::Paid && $payment->due_date->lt(today()),
        );

        return [
            'expected' => round($payments->sum(fn (Payment $payment): float => (float) $payment->amount), 2),
            'received' => round($payments->sum(fn (Payment $payment): float => (float) $payment->received_amount), 2),
            'open' => round($payments->sum($outstanding), 2),
            'overdue' => round($overduePayments->sum($outstanding), 2),
            'overdue_count' => $overduePayments->count(),
        ];
    }
}
