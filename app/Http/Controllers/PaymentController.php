<?php

namespace App\Http\Controllers;

use App\Actions\Leases\SyncLeasePayments;
use App\Actions\Payments\SummarizePayments;
use App\Http\Controllers\Concerns\ResolvesMonthFilter;
use App\Models\Lease;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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
        ]);
    }
}
