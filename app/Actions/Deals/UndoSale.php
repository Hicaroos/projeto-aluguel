<?php

namespace App\Actions\Deals;

use App\Enums\PropertyStatus;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class UndoSale
{
    public function __construct(private MoveDeal $moveDeal) {}

    /**
     * Remove a sale registered by mistake: the property is offered again as it was, its deal goes
     * back to the last stage before closing, and the deals lost because of the sale come back to
     * the start of the funnel. A lease terminated by the sale stays terminated.
     */
    public function handle(Sale $sale): void
    {
        DB::transaction(function () use ($sale): void {
            $property = $sale->property;

            if ($property->status === PropertyStatus::Sold) {
                $property->update([
                    'status' => PropertyStatus::Available,
                    'for_sale' => true,
                    'for_rent' => $sale->property_was_for_rent,
                ]);
            } else {
                $property->update(['for_sale' => true]);
            }

            $openStages = DealStage::where('account_id', $sale->account_id)->open()->orderBy('sort_order')->orderBy('id')->get();
            $deal = Deal::withoutGlobalScopes()->find($sale->deal_id);

            if ($deal !== null && ! $deal->isOpen() && $openStages->isNotEmpty()) {
                $this->moveDeal->handle($deal, $openStages->last());
            }

            if ($openStages->isNotEmpty()) {
                Deal::withoutGlobalScopes()
                    ->where('account_id', $sale->account_id)
                    ->where('property_id', $sale->property_id)
                    ->whereNotNull('closed_at')
                    ->where('lost_reason', __('Imóvel vendido para outro comprador'))
                    ->get()
                    ->each(fn (Deal $lostDeal) => $this->moveDeal->handle($lostDeal, $openStages->first()));
            }

            $sale->delete();
        });
    }
}
