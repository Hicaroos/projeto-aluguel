<?php

namespace App\Actions\Payouts;

use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterOwnerPayout
{
    /**
     * Record money paid to the owner and take it from their running account.
     *
     * @param  array{amount: float|string, paid_on: string, payment_method?: string|null, notes?: string|null}  $attributes
     */
    public function handle(Owner $owner, array $attributes, User $user): OwnerPayout
    {
        return DB::transaction(function () use ($owner, $attributes, $user): OwnerPayout {
            $payout = OwnerPayout::create([
                ...$attributes,
                'account_id' => $owner->account_id,
                'owner_id' => $owner->id,
                'user_id' => $user->id,
            ]);

            OwnerLedgerEntry::create([
                'account_id' => $owner->account_id,
                'owner_id' => $owner->id,
                'owner_payout_id' => $payout->id,
                'user_id' => $user->id,
                'type' => LedgerEntryType::Payout,
                'description' => $payout->payment_method instanceof PaymentMethod
                    ? "Repasse via {$payout->payment_method->label()}"
                    : 'Repasse',
                'amount' => -round((float) $payout->amount, 2),
                'occurred_on' => $payout->paid_on->toDateString(),
                'available_on' => $payout->paid_on->toDateString(),
            ]);

            return $payout;
        });
    }
}
