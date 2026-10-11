<?php

namespace App\Actions\Payouts;

use App\Enums\AccountType;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Enums\PayoutMode;
use App\Models\Account;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use App\Models\Property;
use Carbon\CarbonImmutable;

/**
 * @phpstan-import-type PayoutRules from ResolvePayoutRules
 */
class CreditGuaranteedRent
{
    /**
     * How far back to look for guaranteed rent not credited yet, e.g. while the scheduler was down.
     */
    private const int LOOKBACK_DAYS = 60;

    public function __construct(
        private ResolvePayoutRules $resolvePayoutRules,
        private PostRentToLedger $postRentToLedger,
    ) {}

    /**
     * Credit the guaranteed rent that has come due, for every agency or only the given account.
     * Safe to run many times: each owner is credited the rent of a month once.
     *
     * @return int How many owner credits were made.
     */
    public function handleDue(?int $accountId = null): int
    {
        $credited = 0;
        $agencyIds = Account::query()
            ->where('type', AccountType::Agency)
            ->when($accountId !== null, fn ($query) => $query->whereKey($accountId))
            ->pluck('id');

        Payment::withoutGlobalScopes()
            ->whereIn('account_id', $agencyIds)
            ->rent()
            ->where('status', '!=', PaymentStatus::Canceled)
            ->whereDate('due_date', '<=', today())
            ->whereDate('due_date', '>=', today()->subDays(self::LOOKBACK_DAYS))
            ->orderBy('id')
            ->each(function (Payment $payment) use (&$credited): void {
                $credited += $this->handle($payment);
            });

        return $credited;
    }

    /**
     * Credit the guaranteed rent of the payment to each owner whose rules guarantee it, once the
     * agreed date has come.
     *
     * @return int How many owner credits were made.
     */
    public function handle(Payment $payment): int
    {
        $lease = Lease::withoutGlobalScopes()->withTrashed()->find($payment->lease_id);
        $account = $lease !== null ? Account::find($lease->account_id) : null;

        if ($lease === null || $account === null || ! $account->isAgency()) {
            return 0;
        }

        $property = Property::withoutGlobalScopes()->withTrashed()->findOrFail($lease->property_id);
        $credited = 0;

        foreach ($property->ownerShares() as $ownerId => $share) {
            $owner = Owner::withoutGlobalScopes()->withTrashed()->find($ownerId);

            if ($owner === null) {
                continue;
            }

            $rules = $this->resolvePayoutRules->handle($account, $owner, $lease);

            if ($rules['payout_mode'] === PayoutMode::Guaranteed && $this->guaranteedOn($payment, $rules)->lte(today())) {
                $credited += $this->forOwner($payment, $owner, $share, $rules) ? 1 : 0;
            }
        }

        return $credited;
    }

    /**
     * Credit one owner the guaranteed rent of the payment, payable on the agreed date, unless they
     * were already credited the rent of that month.
     *
     * @param  PayoutRules  $rules
     */
    public function forOwner(Payment $payment, Owner $owner, float $share, array $rules): bool
    {
        $alreadyCredited = OwnerLedgerEntry::withoutGlobalScopes()
            ->where('payment_id', $payment->id)
            ->where('owner_id', $owner->id)
            ->where('type', LedgerEntryType::Rent)
            ->exists();

        if ($alreadyCredited) {
            return false;
        }

        $guaranteedOn = $this->guaranteedOn($payment, $rules);

        $this->postRentToLedger->handle(
            $payment,
            $owner,
            $share,
            $rules,
            (float) $payment->amount,
            $guaranteedOn,
            $guaranteedOn,
            guaranteed: true,
        );

        return true;
    }

    /**
     * Determine whether the owner was credited the rent of the payment as guaranteed rent.
     */
    public function wasGuaranteed(Payment $payment, Owner $owner): bool
    {
        return OwnerLedgerEntry::withoutGlobalScopes()
            ->where('payment_id', $payment->id)
            ->where('owner_id', $owner->id)
            ->where('type', LedgerEntryType::Rent)
            ->where('is_guaranteed', true)
            ->exists();
    }

    /**
     * Get the date the agency pays the guaranteed rent: the payout term counted from the due date.
     *
     * @param  PayoutRules  $rules
     */
    private function guaranteedOn(Payment $payment, array $rules): CarbonImmutable
    {
        return $rules['payout_schedule']->payoutDate($payment->due_date, $rules['payout_days'], $rules['payout_day']);
    }
}
