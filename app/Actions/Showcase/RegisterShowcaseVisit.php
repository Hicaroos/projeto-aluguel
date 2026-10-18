<?php

namespace App\Actions\Showcase;

use App\Actions\Deals\EnsureDefaultDealStages;
use App\Actions\Deals\MoveDeal;
use App\Enums\DealType;
use App\Enums\LeadInterest;
use App\Enums\LeadSource;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Lead;
use App\Models\Property;
use Illuminate\Support\Facades\DB;

class RegisterShowcaseVisit
{
    public function __construct(
        private EnsureDefaultDealStages $ensureDefaultDealStages,
        private MoveDeal $moveDeal,
    ) {}

    /**
     * Bring a visit request from the showcase into the funnel: the person as a lead, found again by
     * their phone when they asked before, and a deal for the property in the first stage, unless they
     * already have one open for it. The request goes into the lead notes.
     *
     * @param  array{name: string, phone: string, period: string, purpose: 'sale'|'rent'}  $visit
     */
    public function handle(Property $property, array $visit): Deal
    {
        return DB::transaction(function () use ($property, $visit): Deal {
            $isSale = $visit['purpose'] === 'sale';
            $lead = Lead::query()
                ->where('account_id', $property->account_id)
                ->where('phone', $visit['phone'])
                ->first()
                ?? Lead::create([
                    'account_id' => $property->account_id,
                    'branch_id' => $property->branch_id,
                    'name' => $visit['name'],
                    'phone' => $visit['phone'],
                    'source' => LeadSource::Website,
                    'interest' => $isSale ? LeadInterest::Buy : LeadInterest::Rent,
                    'property_type' => $property->type,
                ]);

            $note = __('Pediu uma visita pela vitrine em :date (REF :ref), de preferência :period.', [
                'date' => now()->format('d/m/Y'),
                'ref' => PresentShowcaseProperty::ref($property),
                'period' => $visit['period'],
            ]);
            $lead->update(['notes' => trim(($lead->notes ?? '')."\n".$note)]);

            $deal = Deal::query()
                ->where('lead_id', $lead->id)
                ->where('property_id', $property->id)
                ->whereNull('closed_at')
                ->first();

            if ($deal !== null) {
                return $deal;
            }

            $firstStage = $this->ensureDefaultDealStages->handle($property->account)->first(fn (DealStage $stage): bool => $stage->isOpen());

            $deal = Deal::create([
                'account_id' => $property->account_id,
                'lead_id' => $lead->id,
                'property_id' => $property->id,
                'deal_stage_id' => $firstStage->id,
                'type' => $isSale ? DealType::Sale : DealType::Rent,
                'value' => $isSale ? $property->showcaseSalePrice() : $property->showcaseRent(),
            ]);

            $this->moveDeal->handle($deal, $firstStage);

            return $deal;
        });
    }
}
