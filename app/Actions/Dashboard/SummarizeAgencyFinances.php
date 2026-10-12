<?php

namespace App\Actions\Dashboard;

use App\Actions\Payouts\SummarizeOwnerBalances;
use App\Enums\ExpensePayer;
use App\Enums\ExpenseStatus;
use App\Enums\LedgerEntryType;
use App\Models\Expense;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * @phpstan-type AgencyRevenue array{adminFees: float, intermediationFees: float, renewalFees: float, saleCommissions: float, lateCharges: float, extraCharges: float, expenses: float, result: float}
 * @phpstan-type OwnersMoney array{received: float, available: float, pending: float, advanced: float, paidOut: float}
 */
class SummarizeAgencyFinances
{
    public function __construct(private SummarizeOwnerBalances $summarizeOwnerBalances) {}

    /**
     * Summarize the agency's month: what it earned itself and the owners' money it holds. With an owner
     * picked in the owner selector, only what concerns them, by their share of each property.
     *
     * @return array{revenue: AgencyRevenue, owners: OwnersMoney}
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
        ];
    }

    /**
     * Round an amount to cents, without the negative zero rounding may leave, shown as "-R$ 0,00".
     */
    private function cents(float $value): float
    {
        return round($value, 2) + 0.0;
    }
}
