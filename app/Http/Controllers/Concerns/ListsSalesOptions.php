<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

trait ListsSalesOptions
{
    /**
     * Get the people of the agency who may be in charge of leads, deals and visits.
     * Single owners do everything themselves.
     *
     * @return Collection<int, User>|array{}
     */
    protected function teamMemberOptions(Request $request): Collection|array
    {
        $account = $request->user()->account;

        if (! $account?->isAgency()) {
            return [];
        }

        return User::where('account_id', $account->id)
            ->whereNull('deactivated_at')
            ->whereNull('invitation_token')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Get the properties a deal may be about: offered for sale or rent and not sold yet.
     *
     * @return Collection<int, Property>
     */
    protected function dealPropertyOptions(): Collection
    {
        return Property::query()
            ->where(fn (Builder $query) => $query->where('for_sale', true)->orWhere('for_rent', true))
            ->where('status', '!=', PropertyStatus::Sold)
            ->orderBy('street')
            ->get(['id', 'type', 'street', 'number', 'complement', 'neighborhood', 'city', 'for_rent', 'for_sale', 'rent_amount', 'sale_price', 'status']);
    }
}
