<?php

namespace App\Actions\Showcase;

use App\Enums\LeadInterest;
use App\Enums\LeadSource;
use App\Models\Account;
use App\Models\Lead;

class RegisterShowcaseListing
{
    /**
     * Bring an owner who wants to list a property through the showcase into the leads: found again by
     * their phone when they wrote before, or a new lead who wants to list. The request goes into the notes.
     *
     * @param  array{name: string, phone: string, goal: string, neighborhood: string|null, message: string|null}  $listing
     */
    public function handle(Account $account, array $listing): Lead
    {
        $lead = Lead::query()
            ->where('account_id', $account->id)
            ->where('phone', $listing['phone'])
            ->first()
            ?? Lead::create([
                'account_id' => $account->id,
                'name' => $listing['name'],
                'phone' => $listing['phone'],
                'source' => LeadSource::Website,
                'interest' => LeadInterest::List,
            ]);

        $note = __('Pediu pela vitrine em :date para :goal:where.', [
            'date' => now()->format('d/m/Y'),
            'goal' => $listing['goal'],
            'where' => $listing['neighborhood'] === null ? '' : __(', no bairro :neighborhood', ['neighborhood' => $listing['neighborhood']]),
        ]);

        if ($listing['message'] !== null) {
            $note .= ' '.__('Sobre o imóvel: ":message"', ['message' => $listing['message']]);
        }

        $lead->update(['notes' => trim(($lead->notes ?? '')."\n".$note)]);

        return $lead;
    }
}
