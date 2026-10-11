<?php

namespace App\Actions\Payouts;

use App\Enums\PayoutMode;
use App\Enums\PayoutRule;
use App\Enums\PayoutSchedule;
use App\Models\Account;
use App\Models\Lease;
use App\Models\Owner;
use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-type PayoutRules array{
 *     admin_fee_percent: float,
 *     intermediation_fee_percent: float,
 *     renewal_fee_percent: float,
 *     admin_fee_with_intermediation: bool,
 *     admin_fee_on_extra_charges: bool,
 *     extra_charges_to_owner: bool,
 *     late_charges_owner_percent: float,
 *     payout_mode: PayoutMode,
 *     payout_schedule: PayoutSchedule,
 *     payout_days: int,
 *     payout_day: int,
 * }
 */
class ResolvePayoutRules
{
    /**
     * Get the payout rules that apply: what the lease sets, else what the owner sets, else the
     * agency defaults.
     *
     * @return PayoutRules
     */
    public function handle(Account $account, ?Owner $owner = null, ?Lease $lease = null): array
    {
        $value = fn (PayoutRule $rule): mixed => $this->valueOf($lease, $rule)
            ?? $this->valueOf($owner, $rule)
            ?? $account->getAttribute($rule->value);

        $mode = $value(PayoutRule::PayoutMode);
        $schedule = $value(PayoutRule::PayoutSchedule);

        return [
            'admin_fee_percent' => (float) $value(PayoutRule::AdminFeePercent),
            'intermediation_fee_percent' => (float) $value(PayoutRule::IntermediationFeePercent),
            'renewal_fee_percent' => (float) $value(PayoutRule::RenewalFeePercent),
            'admin_fee_with_intermediation' => (bool) $value(PayoutRule::AdminFeeWithIntermediation),
            'admin_fee_on_extra_charges' => (bool) $value(PayoutRule::AdminFeeOnExtraCharges),
            'extra_charges_to_owner' => (bool) $value(PayoutRule::ExtraChargesToOwner),
            'late_charges_owner_percent' => (float) $value(PayoutRule::LateChargesOwnerPercent),
            'payout_mode' => $mode instanceof PayoutMode ? $mode : PayoutMode::Received,
            'payout_schedule' => $schedule instanceof PayoutSchedule ? $schedule : PayoutSchedule::DaysAfter,
            'payout_days' => (int) $value(PayoutRule::PayoutDays),
            'payout_day' => (int) $value(PayoutRule::PayoutDay),
        ];
    }

    /**
     * Get the value the given record sets for the rule, if any.
     */
    private function valueOf(?Model $record, PayoutRule $rule): mixed
    {
        return $record?->getAttribute($rule->value);
    }
}
