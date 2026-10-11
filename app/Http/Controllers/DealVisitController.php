<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesSelectedRecord;
use App\Http\Requests\DealVisitRequest;
use App\Models\Deal;
use App\Models\DealVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DealVisitController extends Controller
{
    use ResolvesSelectedRecord;

    /**
     * Schedule a visit to the property of the deal.
     */
    public function store(DealVisitRequest $request, Deal $deal): RedirectResponse
    {
        Gate::authorize('update', $deal);

        $deal->visits()->create([
            ...$request->safe()->except('status'),
            'account_id' => $deal->account_id,
            'user_id' => $request->validated('user_id') ?? $deal->user_id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Visita agendada.')]);

        return $this->backWithSelectedRecord($deal->id);
    }

    /**
     * Reschedule the visit or tell how it went.
     */
    public function update(DealVisitRequest $request, DealVisit $visit): RedirectResponse
    {
        Gate::authorize('update', $visit);

        $visit->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Visita atualizada.')]);

        return $this->backWithSelectedRecord($visit->deal_id);
    }

    /**
     * Remove the visit.
     */
    public function destroy(DealVisit $visit): RedirectResponse
    {
        Gate::authorize('delete', $visit);

        $visit->delete();

        return $this->backWithSelectedRecord($visit->deal_id);
    }
}
