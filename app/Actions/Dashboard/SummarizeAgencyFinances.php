<?php

namespace App\Actions\Dashboard;

use App\Actions\Payouts\SummarizeOwnerBalances;
use App\Enums\ExpensePayer;
use App\Enums\ExpenseStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Models\Expense;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * @phpstan-type AgencyRevenue array{adminFees: float, intermediationFees: float, renewalFees: float, saleCommissions: float, lateCharges: float, extraCharges: float, expenses: float, result: float}
 * @phpstan-type OwnersMoney array{received: float, available: float, pending: float, advanced: float, paidOut: float}
 * @phpstan-type Delinquency array{total: float, count: int, rate: float, upTo30: float, upTo60: float, over60: float}
 */
class SummarizeAgencyFinances
{
    public function __construct(private SummarizeOwnerBalances $summarizeOwnerBalances) {}

    /**
     * Summarize the agency's month: what it earned itself, the owners' money it holds and the rent the
     * tenants owe. With an owner picked in the owner selector, only what concerns them, by their share
     * of each property.
     *
     * @return array{revenue: AgencyRevenue, owners: OwnersMoney, delinquency: Delinquency}
     */
    public function handle(CarbonImmutable $month): array
    {
        $ownerId = Owner::selectedId();
        $shares = $ownerId !== null ? Owner::sharesOf($ownerId) : null;
        $share = fn (?int $propertyId): float => $shares === null ? 1.0 : ($shares[$propertyId] ?? 0.0);
        $period = [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()];

        $ledger = OwnerLedgerEntry::query()
            ->whereBetween('occurred_on', $period)
            ->whereIn('property_id', Property::withTrashed()->select('id'))
            ->when($ownerId !== null, fn (Builder $query) => $query->where('owner_id', $ownerId))
            ->groupBy('type')
            ->selectRaw('type, sum(amount) as total')
            ->toBase()
            ->pluck('total', 'type')
            ->map(fn (mixed $total): float => (float) $total);

        $posted = fn (LedgerEntryType $type): float => (float) ($ledger[$type->value] ?? 0.0);

        $receipts = Receipt::query()
            ->whereBetween('date', $period)
            ->with(['payment' => fn ($query) => $query
                ->select(['id', 'lease_id', 'type'])
                ->with(['lease' => fn ($query) => $query->withTrashed()->select(['id', 'property_id'])])])
            ->get(['id', 'payment_id', 'amount', 'late_fee_amount', 'interest_amount']);

        $receiptShare = fn (Receipt $receipt): float => $share($receipt->payment->lease->property_id);
        $lateChargesReceived = $receipts->sum(fn (Receipt $receipt): float => ((float) $receipt->late_fee_amount + (float) $receipt->interest_amount) * $receiptShare($receipt));
        $extraChargesReceived = $receipts
            ->filter(fn (Receipt $receipt): bool => $receipt->payment->isExtra())
            ->sum(fn (Receipt $receipt): float => (float) $receipt->amount * $receiptShare($receipt));

        $revenue = [
            'adminFees' => $this->cents(-$posted(LedgerEntryType::AdminFee)),
            'intermediationFees' => $this->cents(-$posted(LedgerEntryType::IntermediationFee)),
            'renewalFees' => $this->cents(-$posted(LedgerEntryType::RenewalFee)),
            'saleCommissions' => $this->cents(Sale::query()
                ->soldInMonth($month)
                ->whereIn('property_id', Property::withTrashed()->select('id'))
                ->get(['id', 'property_id', 'commission_amount'])
                ->sum(fn (Sale $sale): float => (float) $sale->commission_amount * $share($sale->property_id))),
            // What the tenants paid on top of the rent, less what was passed on to the owners.
            'lateCharges' => $this->cents(max(0, $lateChargesReceived - $posted(LedgerEntryType::LateCharges))),
            'extraCharges' => $this->cents(max(0, $extraChargesReceived - $posted(LedgerEntryType::ExtraCharge))),
            'expenses' => $this->cents(Expense::query()
                ->where('charged_to', ExpensePayer::Agency)
                ->where('status', ExpenseStatus::Paid)
                ->whereBetween('payment_date', $period)
                ->get(['id', 'property_id', 'amount'])
                ->sum(fn (Expense $expense): float => (float) $expense->amount * $share($expense->property_id))),
        ];

        $revenue['result'] = $this->cents(
            $revenue['adminFees'] + $revenue['intermediationFees'] + $revenue['renewalFees'] + $revenue['saleCommissions']
            + $revenue['lateCharges'] + $revenue['extraCharges'] - $revenue['expenses'],
        );

        $ownerIds = array_values(Owner::query()
            ->when($ownerId !== null, fn (Builder $query) => $query->whereKey($ownerId))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
        $totals = $this->summarizeOwnerBalances->totals($this->summarizeOwnerBalances->balances($ownerIds), $month);

        return [
            'revenue' => $revenue,
            'owners' => [
                'received' => $this->cents($receipts->sum(
                    fn (Receipt $receipt): float => ((float) $receipt->amount + (float) $receipt->late_fee_amount + (float) $receipt->interest_amount) * $receiptShare($receipt),
                )),
                'available' => $totals['available'],
                'pending' => $totals['pending'],
                'advanced' => $this->summarizeOwnerBalances->advancedRent($ownerIds),
                'paidOut' => $this->cents(-(float) OwnerLedgerEntry::query()
                    ->whereIn('owner_id', $ownerIds)
                    ->where('type', LedgerEntryType::Payout)
                    ->whereBetween('occurred_on', $period)
                    ->sum('amount')),
            ],
            'delinquency' => $this->delinquency($month, $share),
        ];
    }

    /**
     * Sum up the rent overdue by how late it is, and how much of what fell due this month is still unpaid.
     *
     * @param  Closure(?int): float  $share
     * @return Delinquency
     */
    private function delinquency(CarbonImmutable $month, Closure $share): array
    {
        $outstanding = fn (Payment $payment): float => max(0, (float) $payment->amount - (float) $payment->received_amount)
            * $share($payment->lease->property_id);

        $overdue = $this->payments()->overdue()->get(['id', 'lease_id', 'amount', 'due_date']);
        $daysLate = fn (Payment $payment): int => (int) $payment->due_date->diffInDays(today());

        $dueSoFar = $this->payments()
            ->dueInMonth($month)
            ->whereDate('due_date', '<', today())
            ->get(['id', 'lease_id', 'amount', 'due_date']);
        $expected = $dueSoFar->sum(fn (Payment $payment): float => (float) $payment->amount * $share($payment->lease->property_id));

        return [
            'total' => $this->cents($overdue->sum($outstanding)),
            'count' => $overdue->count(),
            'rate' => $expected > 0 ? round($dueSoFar->sum($outstanding) / $expected * 100, 1) : 0.0,
            'upTo30' => $this->cents($overdue->filter(fn (Payment $payment): bool => $daysLate($payment) <= 30)->sum($outstanding)),
            'upTo60' => $this->cents($overdue->filter(fn (Payment $payment): bool => $daysLate($payment) > 30 && $daysLate($payment) <= 60)->sum($outstanding)),
            'over60' => $this->cents($overdue->filter(fn (Payment $payment): bool => $daysLate($payment) > 60)->sum($outstanding)),
        ];
    }

    /**
     * @return Builder<Payment>
     */
    private function payments(): Builder
    {
        return Payment::query()
            ->whereHas('lease')
            ->where('status', '!=', PaymentStatus::Canceled)
            ->withSum('receipts as received_amount', 'amount')
            ->with(['lease' => fn ($query) => $query->select(['id', 'property_id'])]);
    }

    /**
     * Round an amount to cents, without the negative zero rounding may leave, shown as "-R$ 0,00".
     */
    private function cents(float $value): float
    {
        return round($value, 2) + 0.0;
    }
}
