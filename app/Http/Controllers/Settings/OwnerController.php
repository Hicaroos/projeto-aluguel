<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\OwnerUpdateRequest;
use App\Models\Owner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OwnerController extends Controller
{
    /**
     * Show the owner details used in lease contracts.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Owner', [
            'owner' => $this->owner($request),
        ]);
    }

    /**
     * Update the owner details used in lease contracts. The owner is the user of a single owner account,
     * so both keep the same name.
     */
    public function update(OwnerUpdateRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $this->owner($request)->update($request->validated());
            $request->user()->update(['name' => $request->validated('name')]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Dados do proprietário atualizados.')]);

        return to_route('owner.edit');
    }

    /**
     * Get the owner of a single owner account. Agencies manage several owners elsewhere.
     */
    private function owner(Request $request): Owner
    {
        $account = $request->user()->account;
        $owner = $account?->isAgency() ? null : $account?->primaryOwner();

        abort_if($owner === null, 404);

        return $owner;
    }
}
