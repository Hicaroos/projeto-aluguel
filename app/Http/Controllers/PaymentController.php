<?php

namespace App\Http\Controllers;

use App\Actions\Leases\SyncLeasePayments;
use App\Actions\Payments\CreateExtraCharge;
use App\Actions\Payments\SummarizePayments;
use App\Enums\LeaseStatus;
use App\Http\Controllers\Concerns\ResolvesMonthFilter;
use App\Http\Requests\ExtraChargeRequest;
use App\Models\Lease;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    use ResolvesMonthFilter;

    /**
     * Display the authenticated account's payments for a month or for a single lease.
     */
    public function index(Request $request, SyncLeasePayments $syncLeasePayments, SummarizePayments $summarizePayments): Response
    {
        $accountId = $request->user()->account_id;

        $syncLeasePayments->handleMissingForAccount($accountId);

        $month = $this->resolveMonth($request->string('month')->toString());
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $lease = $request->integer('lease') > 0
            ? Lease::where('account_id', $accountId)
                ->with(['tenant:id,name,deleted_at', 'property:id,street,number,deleted_at'])
                ->find($request->integer('lease'))
            : null;

        $scopedQuery = fn (): Builder => Payment::where('account_id', $accountId)
            ->whereHas('lease')
            ->when(
                $lease !== null,
                fn (Builder $query) => $query->where('lease_id', $lease->id),
                fn (Builder $query) => $query->dueInMonth($month),
            );

        return Inertia::render('payments/Index', [
            'payments' => $scopedQuery()
                ->withListDetails()
                ->filterByStatus($status)
                ->search($search)
                ->orderBy('due_date')
                ->orderBy('id')
                ->paginate(15)
                ->withQueryString(),
            'summary' => $summarizePayments->handle($scopedQuery()),
            'filters' => [
                'month' => $month->format('Y-m'),
                'search' => $search,
                'status' => $status !== '' ? $status : null,
                'lease' => $lease?->id,
            ],
            'lease' => $lease,
            'leaseOptions' => Lease::where('account_id', $accountId)
                ->with(['tenant:id,name,deleted_at', 'property:id,street,number,neighborhood,deleted_at'])
                ->orderByRaw('status = ? desc', [LeaseStatus::Active->value])
                ->latest('start_date')
                ->get(['id', 'tenant_id', 'property_id', 'status']),
        ]);
    }

    /**
     * Charge the tenant of a lease an extra amount, such as a repair or a fine.
     */
    public function store(ExtraChargeRequest $request, CreateExtraCharge $createExtraCharge): RedirectResponse
    {
        $lease = Lease::findOrFail($request->integer('lease_id'));

        $createExtraCharge->handle($lease, [
            'description' => $request->string('description')->toString(),
            'amount' => $request->validated('amount'),
            'due_date' => $request->validated('due_date'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cobrança avulsa cadastrada com sucesso.')]);

        return back();
    }

    /**
     * Remove the given extra charge.
     */
    public function destroy(Payment $payment): RedirectResponse
    {
        Gate::authorize('delete', $payment);

        $payment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cobrança avulsa removida com sucesso.')]);

        return back();
    }
}
