<?php

namespace App\Http\Controllers;

use App\Actions\Leases\ApplyLeaseAdjustment;
use App\Http\Requests\LeaseAdjustmentRequest;
use App\Models\Lease;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Inertia\Inertia;

class LeaseAdjustmentController extends Controller
{
    /**
     * Apply the annual rent adjustment of the given lease.
     */
    public function store(LeaseAdjustmentRequest $request, Lease $lease, ApplyLeaseAdjustment $applyLeaseAdjustment): RedirectResponse
    {
        Gate::authorize('adjust', $lease);

        $adjustment = $applyLeaseAdjustment->handle($lease, $request->change(), $request->validated('notes'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Reajuste aplicado: :from → :to.', [
                'from' => Number::currency((float) $adjustment->previous_amount, 'BRL', 'pt_BR'),
                'to' => Number::currency((float) $adjustment->new_amount, 'BRL', 'pt_BR'),
            ]),
        ]);

        return back();
    }
}
