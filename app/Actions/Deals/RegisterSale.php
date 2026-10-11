<?php

namespace App\Actions\Deals;

use App\Actions\Leases\FinishLease;
use App\Enums\DealOutcome;
use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Enums\RentalOutcome;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Property;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RegisterSale
{
    public function __construct(private MoveDeal $moveDeal, private FinishLease $finishLease) {}

    /**
     * Register the sale of the property, closing the deal it came from, if any: the property
     * leaves the portfolio as sold, and the other deals for the same property are lost. A rented
     * property either has its lease terminated on the sale date, or stays in the portfolio as
     * rented when the lease carries on.
     *
     * @param  array<string, mixed>  $attributes  The validated sale details.
     */
    public function handle(Property $property, array $attributes, ?Deal $deal = null): Sale
    {
        return DB::transaction(function () use ($property, $deal, $attributes): Sale {
            $commissionPercent = $attributes['commission_percent'] ?? null;

            $sale = Sale::create([
                ...$attributes,
                'account_id' => $property->account_id,
                'property_id' => $property->id,
                'deal_id' => $deal?->id,
                'lead_id' => $deal?->lead_id,
                'seller_owner_id' => $property->owner_id,
                'property_was_for_rent' => $property->for_rent,
                'rental_outcome' => $property->activeLease !== null ? ($attributes['rental_outcome'] ?? null) : null,
                'commission_amount' => $commissionPercent === null
                    ? null
                    : round((float) $attributes['price'] * (float) $commissionPercent / 100, 2),
            ]);

            $activeLease = $property->activeLease;
            $rentalOutcome = $sale->rental_outcome;

            if ($activeLease !== null && $rentalOutcome === RentalOutcome::Keep) {
                $property->update(['for_sale' => false]);
            } else {
                if ($activeLease !== null) {
                    $this->finishLease->handle($activeLease, LeaseStatus::Terminated);
                }

                $property->refresh()->update(['status' => PropertyStatus::Sold, 'for_sale' => false, 'for_rent' => false]);
            }

            if ($deal !== null) {
                $deal->update(['value' => $attributes['price']]);
                $this->moveDeal->handle($deal, $this->stage($property, DealOutcome::Won));
            }

            $otherDeals = Deal::where('account_id', $property->account_id)
                ->where('property_id', $property->id)
                ->when($deal !== null, fn (Builder $query) => $query->whereKeyNot($deal->id))
                ->whereNull('closed_at')
                ->get();

            if ($otherDeals->isNotEmpty()) {
                $lostStage = $this->stage($property, DealOutcome::Lost);

                $otherDeals->each(fn (Deal $otherDeal) => $this->moveDeal->handle($otherDeal, $lostStage, lostReason: __('Imóvel vendido para outro comprador')));
            }

            return $sale;
        });
    }

    /**
     * Get the stage of the account's funnel for the given outcome.
     */
    private function stage(Property $property, DealOutcome $outcome): DealStage
    {
        return DealStage::where('account_id', $property->account_id)->where('outcome', $outcome)->firstOrFail();
    }
}
