<?php

namespace App\Http\Controllers;

use App\Actions\Leases\SyncLeasePayments;
use App\Actions\Payments\SummarizePayments;
use App\Enums\PaymentStatus;
use App\Models\Lease;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
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
                fn (Builder $query) => $query->whereBetween('due_date', [$month->toDateString(), $month->endOfMonth()->toDateString()]),
            );

        $payments = $scopedQuery()
            ->with([
                'lease:id,property_id,tenant_id,due_day,status',
                'lease.tenant:id,name,deleted_at',
                'lease.property:id,type,street,number,complement,neighborhood,city,state,deleted_at',
                'receipts' => fn ($query) => $query->orderBy('date')->orderBy('id'),
            ])
            ->withSum('receipts as received_amount', 'amount')
            ->when($status === 'overdue', fn (Builder $query) => $query->overdue())
            ->when(PaymentStatus::tryFrom($status) !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('lease', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                $query->whereHas('tenant', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('property', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                        $query->where('street', 'like', "%{$search}%")
                            ->orWhere('neighborhood', 'like', "%{$search}%");
                    }));
            })))
            ->orderBy('due_date')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('payments/Index', [
            'payments' => $payments,
            'summary' => $summarizePayments->handle($scopedQuery()),
            'filters' => [
                'month' => $month->format('Y-m'),
                'search' => $search,
                'status' => $status !== '' ? $status : null,
                'lease' => $lease?->id,
            ],
            'lease' => $lease,
        ]);
    }

    /**
     * Resolve the requested month (YYYY-MM), falling back to the current month.
     */
    private function resolveMonth(string $month): CarbonImmutable
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
            return CarbonImmutable::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();
        }

        return CarbonImmutable::today()->startOfMonth();
    }
}
