<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

class SummarizePayments
{
    /**
     * Summarize the expected, received, open and overdue amounts of the given payments, plus the late
     * fees and interest received on top of them.
     *
     * Canceled payments are left out of every figure. With the shares of an owner, keyed by lease id, each
     * amount counts only for the part of the property the owner holds.
     *
     * @param  Builder<Payment>  $query
     * @param  array<int, float>|null  $leaseShares
     * @return array{expected: float, received: float, charges: float, open: float, overdue: float, overdue_count: int}
     */
    public function handle(Builder $query, ?array $leaseShares = null): array
    {
        $payments = $query
            ->where('status', '!=', PaymentStatus::Canceled)
            ->withSum('receipts as received_amount', 'amount')
            ->withSum('receipts as late_fee_received', 'late_fee_amount')
            ->withSum('receipts as interest_received', 'interest_amount')
            ->get(['id', 'lease_id', 'amount', 'status', 'due_date']);

        $share = fn (Payment $payment): float => $leaseShares === null ? 1.0 : ($leaseShares[$payment->lease_id] ?? 1.0);
        $outstanding = fn (Payment $payment): float => max(0, (float) $payment->amount - (float) $payment->received_amount) * $share($payment);

        $overduePayments = $payments->filter(
            fn (Payment $payment): bool => $payment->status !== PaymentStatus::Paid && $payment->due_date->lt(today()),
        );

        return [
            'expected' => round($payments->sum(fn (Payment $payment): float => (float) $payment->amount * $share($payment)), 2),
            'received' => round($payments->sum(fn (Payment $payment): float => (float) $payment->received_amount * $share($payment)), 2),
            'charges' => round($payments->sum(
                fn (Payment $payment): float => ((float) $payment->getAttribute('late_fee_received') + (float) $payment->getAttribute('interest_received')) * $share($payment),
            ), 2),
            'open' => round($payments->sum($outstanding), 2),
            'overdue' => round($overduePayments->sum($outstanding), 2),
            'overdue_count' => $overduePayments->count(),
        ];
    }
}
