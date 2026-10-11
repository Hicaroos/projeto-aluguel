<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Concerns\ResolvesSelectedRecord;
use App\Http\Requests\DealProposalRequest;
use App\Models\Deal;
use App\Models\DealProposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DealProposalController extends Controller
{
    use ResolvesSelectedRecord;

    /**
     * Record a proposal made by the lead of the deal.
     */
    public function store(DealProposalRequest $request, Deal $deal): RedirectResponse
    {
        Gate::authorize('update', $deal);

        $deal->proposals()->create([
            ...$request->safe()->except('status'),
            'account_id' => $deal->account_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Proposta registrada.')]);

        return $this->backWithSelectedRecord($deal->id);
    }

    /**
     * Update the proposal or its answer. An accepted proposal sets the value of the deal.
     */
    public function update(DealProposalRequest $request, DealProposal $proposal): RedirectResponse
    {
        Gate::authorize('update', $proposal);

        DB::transaction(function () use ($request, $proposal): void {
            $proposal->update($request->validated());

            if ($proposal->status === ProposalStatus::Accepted) {
                $proposal->deal->update(['value' => $proposal->amount]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Proposta atualizada.')]);

        return $this->backWithSelectedRecord($proposal->deal_id);
    }

    /**
     * Remove the proposal.
     */
    public function destroy(DealProposal $proposal): RedirectResponse
    {
        Gate::authorize('delete', $proposal);

        $proposal->delete();

        return $this->backWithSelectedRecord($proposal->deal_id);
    }
}
