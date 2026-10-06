<?php

namespace App\Http\Controllers;

use App\Actions\Leases\SyncLeasePayments;
use App\Actions\Payments\CalculateLateCharges;
use App\Actions\Payments\CreateExtraCharge;
use App\Actions\Payments\SummarizePayments;
use App\Enums\LeaseStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Concerns\ResolvesMonthFilter;
use App\Http\Controllers\Concerns\SortsTable;
use App\Http\Requests\ExtraChargeRequest;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use SortDirection;

class PaymentController extends Controller
{
    use ResolvesMonthFilter, SortsTable;

    /**
     * Display the authenticated account's payments for a month or for a single lease.
     */
    public function index(
        Request $request,
        SyncLeasePayments $syncLeasePayments,
        SummarizePayments $summarizePayments,
        CalculateLateCharges $calculateLateCharges,
    ): Response {
        $accountId = $request->user()->account_id;

        $syncLeasePayments->handleMissingForAccount($accountId);

        $month = $this->resolveMonth($request->string('month')->toString());
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $type = $request->enum('type', PaymentType::class);
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

        $list = $scopedQuery()
            ->withListDetails()
            ->filterByStatus($status)
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type))
            ->search($search);

        $sorting = $this->applySort($list, $request, [
            'tenant' => fn (Builder $query, SortDirection $direction) => $query->orderBy(
                Tenant::withTrashed()
                    ->select('tenants.name')
                    ->join('leases', 'leases.tenant_id', '=', 'tenants.id')
                    ->whereColumn('leases.id', 'payments.lease_id')
                    ->limit(1),
                $direction,
            ),
            'reference' => fn (Builder $query, SortDirection $direction) => $query->orderBy(new Expression('COALESCE(reference_month, DATE(created_at))'), $direction),
            'due_date' => fn (Builder $query, SortDirection $direction) => $query->orderBy('due_date', $direction),
            'amount' => fn (Builder $query, SortDirection $direction) => $query->orderBy('amount', $direction),
            'status' => function (Builder $query, SortDirection $direction): void {
                $query->orderBy(new Expression("CASE status WHEN 'pending' THEN 0 WHEN 'partial' THEN 1 WHEN 'paid' THEN 2 ELSE 3 END"), $direction);
                $query->orderBy('due_date');
            },
        ], default: 'due_date');

        return Inertia::render('payments/Index', [
            'payments' => $list
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Payment $payment): Payment => $payment->setAttribute(
                    'late_charges_today',
                    $calculateLateCharges->handle(
                        $payment,
                        max(0, (float) $payment->amount - (float) $payment->received_amount),
                        today(),
                    ),
                )),
            'summary' => $summarizePayments->handle($scopedQuery()),
            'filters' => [
                'month' => $month->format('Y-m'),
                'search' => $search,
                'status' => $status !== '' ? $status : null,
                'type' => $type?->value,
                'lease' => $lease?->id,
                ...$sorting,
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
