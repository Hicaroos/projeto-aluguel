<?php

namespace App\Http\Controllers;

use App\Enums\DealType;
use App\Models\Deal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DealLeaseController extends Controller
{
    /**
     * Turn a rent deal into its lease: straight to the lease form when the lead is already a tenant,
     * else first to the tenant form, filled with the lead's details.
     */
    public function __invoke(Deal $deal): RedirectResponse
    {
        Gate::authorize('update', $deal);

        abort_unless($deal->type === DealType::Rent, 404);

        return $deal->leadTenant() === null
            ? to_route('tenants.index', ['negocio' => $deal->id])
            : to_route('leases.index', ['negocio' => $deal->id]);
    }
}
