<?php

namespace App\Http\Controllers;

use App\Actions\Leases\SettleLeaseDeposit;
use App\Http\Requests\SettleDepositRequest;
use App\Models\Lease;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Inertia\Inertia;

class LeaseDepositController extends Controller
{
    /**
     * Settle the deposit of the given finished lease.
     */
    public function store(SettleDepositRequest $request, Lease $lease, SettleLeaseDeposit $settleLeaseDeposit): RedirectResponse
    {
        Gate::authorize('settleDeposit', $lease);

        $settleLeaseDeposit->handle(
            $lease,
            $request->paymentIds(),
            $request->deductions(),
            CarbonImmutable::parse($request->validated('settled_on')),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Caução acertada: :amount devolvidos ao inquilino.', [
                'amount' => Number::currency((float) $lease->fresh()?->deposit_refunded_amount, 'BRL', 'pt_BR'),
            ]),
        ]);

        return back();
    }
}
