<?php

namespace App\Http\Controllers\Settings;

use App\Enums\PayoutRule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PayoutSettingsRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayoutSettingsController extends Controller
{
    /**
     * Show the agency's default fees and payout terms, which owners and leases may set otherwise.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Payouts', [
            'rules' => $this->agency($request)->only(PayoutRule::columns()),
        ]);
    }

    /**
     * Update the agency's default fees and payout terms.
     */
    public function update(PayoutSettingsRequest $request): RedirectResponse
    {
        $this->agency($request)->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Taxas e repasses atualizados.')]);

        return to_route('payout-settings.edit');
    }

    /**
     * Get the account of the signed in agency. Single owners receive their rent themselves.
     */
    private function agency(Request $request): Account
    {
        $account = $request->user()->account;

        abort_unless($account?->isAgency() ?? false, 404);

        return $account;
    }
}
