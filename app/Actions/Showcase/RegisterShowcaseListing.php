<?php

namespace App\Actions\Showcase;

use App\Enums\LeadInterest;
use App\Enums\LeadSource;
use App\Models\Account;
use App\Models\Lead;
use App\Models\ShowcaseRequest;
use Illuminate\Support\Facades\DB;

class RegisterShowcaseListing
{
    /**
     * Bring an owner who wants to list a property through the showcase into the leads: found again by
     * their phone when they wrote before, or a new lead who wants to list. The request is kept apart
     * from the lead's notes, since anyone can send one with any phone.
     *
     * @param  array{name: string, phone: string, goal: string, neighborhood: string|null, message: string|null}  $listing
     */
    public function handle(Account $account, array $listing): Lead
    {
        return DB::transaction(function () use ($account, $listing): Lead {
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

            ShowcaseRequest::create([
                'account_id' => $account->id,
                'lead_id' => $lead->id,
                'name' => $listing['name'],
                'summary' => __('Pediu para :goal:where.', [
                    'goal' => $listing['goal'],
                    'where' => $listing['neighborhood'] === null ? '' : __(', no bairro :neighborhood', ['neighborhood' => $listing['neighborhood']]),
                ]),
                'message' => $listing['message'],
            ]);

            return $lead;
        });
    }
}
