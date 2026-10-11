<?php

namespace App\Actions\Payouts;

use App\Enums\LedgerEntryType;
use App\Models\Lease;
use App\Models\LeaseRenewal;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use App\Models\Receipt;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * @phpstan-import-type PayoutRules from ResolvePayoutRules
 */
class PostRentToLedger
{
    /**
     * Credit an owner their share of rent, received or guaranteed, and take the agency fees from it:
     * the intermediation fee on the first rent of the lease, the renewal fee on the first rent after a
     * renewal (each charged once), and the monthly admin fee, unless the rules leave it out in the
     * month of those fees.
     *
     * @param  PayoutRules  $rules
     * @param  float  $amount  The rent credited, before the owner's share.
     */
    public function handle(
        Payment $payment,
        Owner $owner,
        float $share,
        array $rules,
        float $amount,
        CarbonImmutable $occurredOn,
        CarbonImmutable $availableOn,
        ?Receipt $receipt = null,
        bool $guaranteed = false,
    ): void {
        /** @var Lease $lease */
        $lease = $payment->lease()->withoutGlobalScopes()->withTrashed()->first();
        $month = $this->monthName($payment);

        $base = [
            'account_id' => $payment->account_id,
            'owner_id' => $owner->id,
            'property_id' => $lease->property_id,
            'lease_id' => $lease->id,
            'payment_id' => $payment->id,
            'receipt_id' => $receipt?->id,
            'share_percent' => $share,
            'occurred_on' => $occurredOn->toDateString(),
            'available_on' => $availableOn->toDateString(),
        ];

        $this->entry($base, [
            'type' => LedgerEntryType::Rent,
            'description' => $guaranteed ? "Aluguel garantido de {$month}" : "Aluguel de {$month}",
            'amount' => $this->shareOf($amount, $share),
            'base_amount' => $amount,
            'is_guaranteed' => $guaranteed,
        ]);

        $signingFee = $this->signingFee($payment, $lease, $rules);

        if ($signingFee !== null && ! $this->alreadyCharged($payment, $owner, $signingFee['type'])) {
            $rentAmount = (float) $payment->amount;

            $this->entry($base, [
                'type' => $signingFee['type'],
                'description' => sprintf('%s (%s%% do 1º aluguel)', $signingFee['type']->label(), $this->percent($signingFee['rate'])),
                'amount' => -$this->shareOf($rentAmount * $signingFee['rate'] / 100, $share),
                'base_amount' => $rentAmount,
                'rate_percent' => $signingFee['rate'],
            ]);
        }

        $skipsAdminFee = $signingFee !== null && ! $rules['admin_fee_with_intermediation'];

        if (! $skipsAdminFee && $rules['admin_fee_percent'] > 0) {
            $this->entry($base, [
                'type' => LedgerEntryType::AdminFee,
                'description' => sprintf('Taxa de administração (%s%%)', $this->percent($rules['admin_fee_percent'])),
                'amount' => -$this->shareOf($amount * $rules['admin_fee_percent'] / 100, $share),
                'base_amount' => $amount,
                'rate_percent' => $rules['admin_fee_percent'],
            ]);
        }
    }

    /**
     * Get the one-off fee the rent of the payment carries, if any: the intermediation fee on the
     * first month of the lease, or the renewal fee on the first month after a renewal.
     *
     * @param  PayoutRules  $rules
     * @return array{type: LedgerEntryType, rate: float}|null
     */
    private function signingFee(Payment $payment, Lease $lease, array $rules): ?array
    {
        $referenceMonth = $payment->reference_month?->toDateString();

        if ($referenceMonth === null) {
            return null;
        }

        if ($rules['intermediation_fee_percent'] > 0 && $referenceMonth === $lease->start_date->copy()->startOfMonth()->toDateString()) {
            return ['type' => LedgerEntryType::IntermediationFee, 'rate' => $rules['intermediation_fee_percent']];
        }

        $renewed = $rules['renewal_fee_percent'] > 0 && $lease->renewals()->get()->contains(
            fn (LeaseRenewal $renewal): bool => $renewal->previous_end_date->copy()->addDay()->startOfMonth()->toDateString() === $referenceMonth,
        );

        return $renewed ? ['type' => LedgerEntryType::RenewalFee, 'rate' => $rules['renewal_fee_percent']] : null;
    }

    /**
     * Determine whether the owner was already charged the one-off fee for the payment, e.g. on an
     * earlier part of a rent paid in installments.
     */
    private function alreadyCharged(Payment $payment, Owner $owner, LedgerEntryType $type): bool
    {
        return OwnerLedgerEntry::withoutGlobalScopes()
            ->where('payment_id', $payment->id)
            ->where('owner_id', $owner->id)
            ->where('type', $type)
            ->exists();
    }

    /**
     * Create an entry with the shared details.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $attributes
     */
    private function entry(array $base, array $attributes): void
    {
        OwnerLedgerEntry::create([...$base, ...$attributes]);
    }

    /**
     * Get the owner's share of an amount, rounded to cents.
     */
    private function shareOf(float $amount, float $share): float
    {
        return round($amount * $share / 100, 2);
    }

    /**
     * Format a rate for a description, e.g. "8" or "8,5".
     */
    private function percent(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 2, ',', ''), '0'), ',');
    }

    /**
     * Get the reference month of the rent in Brazilian Portuguese, e.g. "outubro de 2026".
     */
    private function monthName(Payment $payment): string
    {
        return $this->translatedMonth($payment->reference_month ?? $payment->due_date);
    }

    /**
     * Format a month in Brazilian Portuguese.
     */
    private function translatedMonth(CarbonInterface $date): string
    {
        return $date->locale('pt_BR')->translatedFormat('F \d\e Y');
    }
}
