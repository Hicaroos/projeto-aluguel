<?php

namespace App\Actions\Payouts;

use App\Enums\LedgerEntryType;
use App\Models\Account;
use App\Models\OwnerLedgerEntry;
use App\Models\Property;
use App\Models\Sale;

class PostSaleToLedger
{
    /**
     * Keep the sellers' running accounts in line with the sale: when the agency received the down
     * payment, each owner of the property is credited their share of it and charged their share of
     * the commission. Changing or undoing the sale redoes or removes those entries.
     */
    public function handle(Sale $sale): void
    {
        OwnerLedgerEntry::withoutGlobalScopes()->where('sale_id', $sale->id)->delete();

        $account = Account::find($sale->account_id);
        $downPayment = (float) $sale->down_payment;

        if (! ($account?->isAgency() ?? false) || ! $sale->down_payment_received_by_agency || $downPayment <= 0) {
            return;
        }

        $property = Property::withoutGlobalScopes()->withTrashed()->findOrFail($sale->property_id);
        $commission = (float) $sale->commission_amount;
        $address = "{$property->street}, {$property->number}";
        $base = [
            'account_id' => $sale->account_id,
            'property_id' => $property->id,
            'sale_id' => $sale->id,
            'occurred_on' => $sale->sold_on->toDateString(),
            'available_on' => $sale->sold_on->toDateString(),
        ];

        foreach ($property->ownerShares() as $ownerId => $share) {
            OwnerLedgerEntry::create([
                ...$base,
                'owner_id' => $ownerId,
                'type' => LedgerEntryType::SaleDownPayment,
                'description' => "Sinal da venda de {$address}",
                'amount' => round($downPayment * $share / 100, 2),
                'base_amount' => $downPayment,
                'share_percent' => $share,
            ]);

            if ($commission > 0) {
                OwnerLedgerEntry::create([
                    ...$base,
                    'owner_id' => $ownerId,
                    'type' => LedgerEntryType::SaleCommission,
                    'description' => $sale->commission_percent !== null
                        ? sprintf('Comissão da venda (%s%%)', rtrim(rtrim(number_format((float) $sale->commission_percent, 2, ',', ''), '0'), ','))
                        : 'Comissão da venda',
                    'amount' => -round($commission * $share / 100, 2),
                    'base_amount' => (float) $sale->price,
                    'rate_percent' => $sale->commission_percent,
                    'share_percent' => $share,
                ]);
            }
        }
    }
}
