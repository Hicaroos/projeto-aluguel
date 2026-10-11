<?php

namespace App\Actions\Payouts;

use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use Carbon\CarbonInterface;

class SummarizeOwnerBalances
{
    /**
     * Get each owner's balance: what may be paid out today, and what is still waiting for its date.
     *
     * @param  list<int>|null  $ownerIds  Only these owners, or every owner of the account.
     * @return array<int, array{available: float, pending: float, last_payout_on: string|null}>
     */
    public function balances(?array $ownerIds = null): array
    {
        $today = today()->toDateString();

        $rows = OwnerLedgerEntry::query()
            ->when($ownerIds !== null, fn ($query) => $query->whereIn('owner_id', $ownerIds))
            ->selectRaw('owner_id')
            ->selectRaw('sum(case when available_on <= ? then amount else 0 end) as available', [$today])
            ->selectRaw('sum(case when available_on > ? then amount else 0 end) as pending', [$today])
            ->selectRaw('max(case when type = ? then occurred_on end) as last_payout_on', [LedgerEntryType::Payout->value])
            ->groupBy('owner_id')
            ->toBase()
            ->get();

        $balances = [];

        foreach ($rows as $row) {
            $balances[(int) $row->owner_id] = [
                'available' => $this->cents((float) $row->available, 2),
                'pending' => $this->cents((float) $row->pending, 2),
                'last_payout_on' => $row->last_payout_on !== null ? (string) $row->last_payout_on : null,
            ];
        }

        return $balances;
    }

    /**
     * Get the account totals: what to pay out today, what is waiting for its date, the guaranteed
     * rent the agency paid but has not received yet, and the fees it earned in the month.
     *
     * @param  array<int, array{available: float, pending: float, last_payout_on: string|null}>  $ownerBalances
     * @return array{available: float, pending: float, advanced: float, fees: float}
     */
    public function totals(array $ownerBalances, CarbonInterface $month): array
    {
        $balances = collect($ownerBalances);

        return [
            'available' => $this->cents($balances->sum(fn (array $balance): float => max(0, $balance['available'])), 2),
            'pending' => $this->cents($balances->sum(fn (array $balance): float => $balance['pending']), 2),
            'advanced' => $this->advancedRent(),
            'fees' => $this->cents(-(float) OwnerLedgerEntry::query()
                ->whereIn('type', LedgerEntryType::agencyFees())
                ->whereBetween('occurred_on', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->sum('amount'), 2),
        ];
    }

    /**
     * Get the guaranteed rent credited to owners that the tenants still owe the agency.
     */
    private function advancedRent(): float
    {
        $credits = OwnerLedgerEntry::query()
            ->where('type', LedgerEntryType::Rent)
            ->where('is_guaranteed', true)
            ->whereHas('payment', fn ($query) => $query->withoutGlobalScopes()->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Partial]))
            ->with(['payment' => fn ($query) => $query->withoutGlobalScopes()->withSum('receipts as received_amount', 'amount')])
            ->get();

        return $this->cents($credits->sum(function (OwnerLedgerEntry $credit): float {
            /** @var Payment $payment */
            $payment = $credit->payment;
            $owed = max(0, (float) $payment->amount - (float) $payment->received_amount);

            return (float) $payment->amount > 0 ? (float) $credit->amount * $owed / (float) $payment->amount : 0;
        }), 2);
    }

    /**
     * Round an amount to cents, without the negative zero rounding may leave, shown as "-R$ 0,00".
     */
    private function cents(float $value, int $precision = 2): float
    {
        return round($value, $precision) + 0.0;
    }
}
