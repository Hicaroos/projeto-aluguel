<?php

namespace App\Actions\Payouts;

use App\Enums\GuaranteeType;
use App\Enums\LeaseStatus;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use Carbon\CarbonInterface;

/**
 * @phpstan-type StatementEntry array{
 *     id: int,
 *     type: string,
 *     type_label: string,
 *     description: string,
 *     amount: string,
 *     balance: float,
 *     occurred_on: string,
 *     available_on: string,
 *     is_pending: bool,
 *     is_guaranteed: bool,
 *     share_percent: string|null,
 *     owner_payout_id: int|null,
 *     payout_receipt_name: string|null,
 *     is_adjustment: bool,
 *     property: array{id: int, street: string, number: string, complement: string|null}|null,
 * }
 * @phpstan-type HeldDeposit array{lease_id: int, amount: string, tenant: string, property: string}
 */
class BuildOwnerStatement
{
    public function __construct(private SummarizeOwnerBalances $summarizeOwnerBalances) {}

    /**
     * Build the owner's statement for the month: the balance carried over, each entry with the
     * balance after it, the closing balance, what may be paid out today, and the deposits the
     * agency holds on the owner's leases, shown for information only.
     *
     * @return array{
     *     opening_balance: float,
     *     closing_balance: float,
     *     credits: float,
     *     debits: float,
     *     available: float,
     *     pending: float,
     *     entries: list<StatementEntry>,
     *     deposits: list<HeldDeposit>,
     * }
     */
    public function handle(Owner $owner, CarbonInterface $month): array
    {
        $from = $month->copy()->startOfMonth()->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();

        $openingBalance = $this->cents((float) OwnerLedgerEntry::where('owner_id', $owner->id)->whereDate('occurred_on', '<', $from)->sum('amount'), 2);

        $entries = OwnerLedgerEntry::where('owner_id', $owner->id)
            ->whereBetween('occurred_on', [$from, $to])
            ->with(['property:id,street,number,complement', 'ownerPayout:id,receipt_name'])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        $balance = $openingBalance;
        $rows = $entries->map(function (OwnerLedgerEntry $entry) use (&$balance): array {
            $balance = $this->cents($balance + (float) $entry->amount, 2);

            return [
                'id' => $entry->id,
                'type' => $entry->type->value,
                'type_label' => $entry->type->label(),
                'description' => $entry->description,
                'amount' => $entry->amount,
                'balance' => $balance,
                'occurred_on' => $entry->occurred_on->toDateString(),
                'available_on' => $entry->available_on->toDateString(),
                'is_pending' => $entry->available_on->isAfter(today()),
                'is_guaranteed' => $entry->is_guaranteed,
                'share_percent' => $entry->share_percent,
                'owner_payout_id' => $entry->owner_payout_id,
                'payout_receipt_name' => $entry->ownerPayout?->receipt_name,
                'is_adjustment' => $entry->isAdjustment(),
                'property' => $entry->property?->only(['id', 'street', 'number', 'complement']),
            ];
        });

        $current = $this->summarizeOwnerBalances->balances([$owner->id])[$owner->id] ?? ['available' => 0.0, 'pending' => 0.0];

        return [
            'opening_balance' => $openingBalance,
            'closing_balance' => $balance,
            'credits' => $this->cents($entries->sum(fn (OwnerLedgerEntry $entry): float => max(0, (float) $entry->amount)), 2),
            'debits' => $this->cents($entries->sum(fn (OwnerLedgerEntry $entry): float => min(0, (float) $entry->amount)), 2),
            'available' => $current['available'],
            'pending' => $current['pending'],
            'entries' => array_values($rows->all()),
            'deposits' => $this->heldDeposits($owner),
        ];
    }

    /**
     * Get the deposits the agency holds on the active leases of the owner's properties.
     *
     * @return list<HeldDeposit>
     */
    private function heldDeposits(Owner $owner): array
    {
        $deposits = Lease::query()
            ->whereIn('property_id', $owner->properties()->withoutGlobalScopes()->select('properties.id'))
            ->where('status', LeaseStatus::Active)
            ->where('guarantee_type', GuaranteeType::Deposit)
            ->whereNull('deposit_settled_on')
            ->where('deposit_amount', '>', 0)
            ->with(['tenant:id,name,deleted_at', 'property:id,street,number,deleted_at'])
            ->get()
            ->map(fn (Lease $lease): array => [
                'lease_id' => $lease->id,
                'amount' => (string) $lease->deposit_amount,
                'tenant' => $lease->tenant->name,
                'property' => "{$lease->property->street}, {$lease->property->number}",
            ]);

        return array_values($deposits->all());
    }

    /**
     * Round an amount to cents, without the negative zero rounding may leave, shown as "-R$ 0,00".
     */
    private function cents(float $value, int $precision = 2): float
    {
        return round($value, $precision) + 0.0;
    }
}
