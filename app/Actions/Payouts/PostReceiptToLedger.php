<?php

namespace App\Actions\Payouts;

use App\Enums\LedgerEntryType;
use App\Enums\PayoutMode;
use App\Models\Account;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Receipt;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * @phpstan-import-type PayoutRules from ResolvePayoutRules
 */
class PostReceiptToLedger
{
    public function __construct(
        private ResolvePayoutRules $resolvePayoutRules,
        private PostRentToLedger $postRentToLedger,
        private CreditGuaranteedRent $creditGuaranteedRent,
    ) {}

    /**
     * Post what the agency received from the tenant to the running account of each owner of the
     * property, by their share: the rent and its fees (or, for guaranteed rent, nothing more than
     * the guaranteed credit), the extra charges passed on to them and their part of the late
     * charges. Single owners receive their rent themselves, so nothing is posted for them.
     */
    public function handle(Receipt $receipt): void
    {
        $payment = Payment::withoutGlobalScopes()->findOrFail($receipt->payment_id);
        $lease = Lease::withoutGlobalScopes()->withTrashed()->findOrFail($payment->lease_id);
        $account = Account::findOrFail($lease->account_id);

        if (! $account->isAgency()) {
            return;
        }

        $property = Property::withoutGlobalScopes()->withTrashed()->findOrFail($lease->property_id);
        $receivedOn = CarbonImmutable::parse($receipt->date);

        foreach ($property->ownerShares() as $ownerId => $share) {
            $owner = Owner::withoutGlobalScopes()->withTrashed()->find($ownerId);

            if ($owner === null) {
                continue;
            }

            $rules = $this->resolvePayoutRules->handle($account, $owner, $lease);
            $availableOn = $rules['payout_schedule']->payoutDate($receivedOn, $rules['payout_days'], $rules['payout_day']);

            if ($payment->isExtra()) {
                $this->postExtraCharge($receipt, $payment, $lease, $owner, $share, $rules, $availableOn);
            } elseif ($rules['payout_mode'] === PayoutMode::Guaranteed) {
                $this->creditGuaranteedRent->forOwner($payment, $owner, $share, $rules);
            } elseif (! $this->creditGuaranteedRent->wasGuaranteed($payment, $owner)) {
                $this->postRentToLedger->handle($payment, $owner, $share, $rules, (float) $receipt->amount, $receivedOn, $availableOn, $receipt);
            }

            $this->postLateCharges($receipt, $payment, $lease, $owner, $share, $rules, $availableOn);
        }
    }

    /**
     * Credit the owner an extra charge the tenant paid, unless it stays with the agency, taking the
     * admin fee from it when the rules say so.
     *
     * @param  PayoutRules  $rules
     */
    private function postExtraCharge(Receipt $receipt, Payment $payment, Lease $lease, Owner $owner, float $share, array $rules, CarbonImmutable $availableOn): void
    {
        if (! ($payment->pass_to_owner ?? $rules['extra_charges_to_owner'])) {
            return;
        }

        $amount = (float) $receipt->amount;
        $description = $payment->description ?? LedgerEntryType::ExtraCharge->label();
        $base = $this->base($receipt, $payment, $lease, $owner, $share, $availableOn);

        OwnerLedgerEntry::create([
            ...$base,
            'type' => LedgerEntryType::ExtraCharge,
            'description' => $description,
            'amount' => round($amount * $share / 100, 2),
            'base_amount' => $amount,
        ]);

        if ($rules['admin_fee_on_extra_charges'] && $rules['admin_fee_percent'] > 0) {
            OwnerLedgerEntry::create([
                ...$base,
                'type' => LedgerEntryType::AdminFee,
                'description' => sprintf('Taxa de administração sobre %s (%s%%)', mb_strtolower($description), $this->percent($rules['admin_fee_percent'])),
                'amount' => -round($amount * $rules['admin_fee_percent'] / 100 * $share / 100, 2),
                'base_amount' => $amount,
                'rate_percent' => $rules['admin_fee_percent'],
            ]);
        }
    }

    /**
     * Credit the owner their part of the late fee and interest, as chosen on the receipt or, by
     * default, as the rules split them.
     *
     * @param  PayoutRules  $rules
     */
    private function postLateCharges(Receipt $receipt, Payment $payment, Lease $lease, Owner $owner, float $share, array $rules, CarbonImmutable $availableOn): void
    {
        $lateCharges = round((float) $receipt->late_fee_amount + (float) $receipt->interest_amount, 2);
        $ownerPercent = $receipt->late_charges_owner_percent !== null
            ? (float) $receipt->late_charges_owner_percent
            : $rules['late_charges_owner_percent'];

        if ($lateCharges <= 0 || $ownerPercent <= 0) {
            return;
        }

        $month = $this->translatedMonth($payment->reference_month ?? $payment->due_date);

        OwnerLedgerEntry::create([
            ...$this->base($receipt, $payment, $lease, $owner, $share, $availableOn),
            'type' => LedgerEntryType::LateCharges,
            'description' => $ownerPercent < 100
                ? sprintf('Multa e juros de %s (%s%% do proprietário)', $month, $this->percent($ownerPercent))
                : "Multa e juros de {$month}",
            'amount' => round($lateCharges * $ownerPercent / 100 * $share / 100, 2),
            'base_amount' => $lateCharges,
            'rate_percent' => $ownerPercent,
        ]);
    }

    /**
     * Get the details shared by the entries of a receipt.
     *
     * @return array<string, mixed>
     */
    private function base(Receipt $receipt, Payment $payment, Lease $lease, Owner $owner, float $share, CarbonImmutable $availableOn): array
    {
        return [
            'account_id' => $payment->account_id,
            'owner_id' => $owner->id,
            'property_id' => $lease->property_id,
            'lease_id' => $lease->id,
            'payment_id' => $payment->id,
            'receipt_id' => $receipt->id,
            'share_percent' => $share,
            'occurred_on' => CarbonImmutable::parse($receipt->date)->toDateString(),
            'available_on' => $availableOn->toDateString(),
        ];
    }

    /**
     * Format a month in Brazilian Portuguese, e.g. "outubro de 2026".
     */
    private function translatedMonth(CarbonInterface $date): string
    {
        return $date->locale('pt_BR')->translatedFormat('F \d\e Y');
    }

    /**
     * Format a rate for a description, e.g. "8" or "8,5".
     */
    private function percent(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 2, ',', ''), '0'), ',');
    }
}
