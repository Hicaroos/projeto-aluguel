<?php

namespace App\Actions\Payouts;

use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterOwnerPayout
{
    public function __construct(private SummarizeOwnerBalances $summarizeOwnerBalances) {}

    /**
     * Record money paid to the owner and take it from their running account. The owner stays locked
     * while the balance is checked and taken, so two payouts sent at once (a double click) can't
     * together pay more than is available.
     *
     * @param  array{amount: float|string, paid_on: string, payment_method?: string|null, notes?: string|null}  $attributes
     *
     * @throws ValidationException When the amount is more than the owner's available balance.
     */
    public function handle(Owner $owner, array $attributes, User $user): OwnerPayout
    {
        return DB::transaction(function () use ($owner, $attributes, $user): OwnerPayout {
            Owner::withoutGlobalScopes()->whereKey($owner->id)->lockForUpdate()->first();

            $available = round($this->summarizeOwnerBalances->balances([$owner->id])[$owner->id]['available'] ?? 0.0, 2);

            if (round((float) $attributes['amount'], 2) > $available) {
                throw ValidationException::withMessages([
                    'amount' => __('O repasse não pode passar do saldo disponível de :max.', ['max' => 'R$ '.number_format(max(0, $available), 2, ',', '.')]),
                ]);
            }

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
