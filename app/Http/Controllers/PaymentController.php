<?php

namespace App\Http\Controllers;

use App\Actions\Leases\SyncLeasePayments;
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
    public function index(Request $request, SyncLeasePayments $syncLeasePayments): Response
    {
        $accountId = $request->user()->account_id;

        $this->generateMissingPayments($accountId, $syncLeasePayments);

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
            'summary' => $this->summary($scopedQuery()),
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
     * Generate the upcoming payments of active leases that are still missing them.
     *
     * Works as a safety net for when the scheduled generation did not run.
     */
    private function generateMissingPayments(int $accountId, SyncLeasePayments $syncLeasePayments): void
    {
        $nextMonth = today()->addMonthNoOverflow()->startOfMonth();

        Lease::where('account_id', $accountId)
            ->active()
            ->whereDate('end_date', '>=', $nextMonth)
            ->whereDoesntHave('payments', fn (Builder $query) => $query->whereDate('reference_month', $nextMonth))
            ->get()
            ->each(fn (Lease $lease) => $syncLeasePayments->handle($lease));
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

    /**
     * Summarize the expected, received, open and overdue amounts of the given payments.
     *
     * @param  Builder<Payment>  $query
     * @return array{expected: float, received: float, open: float, overdue: float}
     */
    private function summary(Builder $query): array
    {
        $payments = $query
            ->where('status', '!=', PaymentStatus::Canceled)
            ->withSum('receipts as received_amount', 'amount')
            ->get(['id', 'amount', 'status', 'due_date']);

        $outstanding = fn (Payment $payment): float => max(0, (float) $payment->amount - (float) $payment->received_amount);

        return [
            'expected' => round($payments->sum(fn (Payment $payment) => (float) $payment->amount), 2),
            'received' => round($payments->sum(fn (Payment $payment) => (float) $payment->received_amount), 2),
            'open' => round($payments->sum($outstanding), 2),
            'overdue' => round($payments
                ->filter(fn (Payment $payment) => $payment->status !== PaymentStatus::Paid && $payment->due_date->lt(today()))
                ->sum($outstanding), 2),
        ];
    }
}
