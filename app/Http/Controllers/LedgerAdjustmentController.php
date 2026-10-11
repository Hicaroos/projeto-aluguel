<?php

namespace App\Http\Controllers;

use App\Enums\LedgerEntryType;
use App\Http\Requests\LedgerAdjustmentRequest;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class LedgerAdjustmentController extends Controller
{
    /**
     * Enter an adjustment by hand in the owner's running account, for anything the rules do not
     * cover, e.g. a discount agreed with the owner or a refund.
     */
    public function store(LedgerAdjustmentRequest $request, Owner $owner): RedirectResponse
    {
        abort_unless($request->user()->account?->isAgency() ?? false, 404);

        $occurredOn = CarbonImmutable::parse($request->validated('occurred_on'));

        OwnerLedgerEntry::create([
            'account_id' => $owner->account_id,
            'owner_id' => $owner->id,
            'property_id' => $request->validated('property_id'),
            'user_id' => $request->user()->id,
            'type' => LedgerEntryType::Adjustment,
            'description' => $request->validated('description'),
            'amount' => $request->signedAmount(),
            'occurred_on' => $occurredOn->toDateString(),
            'available_on' => $occurredOn->toDateString(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ajuste lançado.')]);

        return to_route('payouts.show', ['owner' => $owner->id, 'month' => $occurredOn->format('Y-m')]);
    }

    /**
     * Remove an adjustment entered by mistake.
     */
    public function destroy(OwnerLedgerEntry $entry): RedirectResponse
    {
        Gate::authorize('delete', $entry);

        $entry->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ajuste excluído.')]);

        return back();
    }
}
