<?php

namespace App\Http\Controllers;

use App\Actions\Leases\SyncLeasePayments;
use App\Actions\Payments\SummarizePayments;
use App\Enums\PaymentStatus;
use App\Enums\PropertyStatus;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the account's overview: month figures, revenue trend and items that need attention.
     */
    public function __invoke(Request $request, SyncLeasePayments $syncLeasePayments, SummarizePayments $summarizePayments): Response
    {
        $accountId = $request->user()->account_id;

        if ($accountId !== null) {
            $syncLeasePayments->handleMissingForAccount($accountId);
        }

        $today = CarbonImmutable::today();
        $month = $today->startOfMonth();
        $accountPayments = fn (): Builder => Payment::where('account_id', $accountId)->whereHas('lease');

        $monthSummary = $summarizePayments->handle(
            $accountPayments()->whereBetween('due_date', [$month->toDateString(), $month->endOfMonth()->toDateString()]),
        );
        $overdueSummary = $summarizePayments->handle($accountPayments()->overdue());

        $properties = Property::where('account_id', $accountId);
        $vacantProperties = (clone $properties)->where('status', PropertyStatus::Available);

        return Inertia::render('Dashboard', [
            'month' => $month->toDateString(),
            'stats' => [
                'expected' => $monthSummary['expected'],
                'received' => $monthSummary['received'],
                'overdue' => $overdueSummary['overdue'],
                'overdueCount' => $overdueSummary['overdue_count'],
                'properties' => (clone $properties)->count(),
                'rentedProperties' => (clone $properties)->where('status', PropertyStatus::Rented)->count(),
                'activeLeases' => Lease::where('account_id', $accountId)->active()->count(),
                'tenants' => Tenant::where('account_id', $accountId)->count(),
            ],
            'monthlyRevenue' => $this->monthlyRevenue($accountPayments, $month),
            'attentionPayments' => $accountPayments()
                ->open()
                ->whereDate('due_date', '<=', $today->addDays(7))
                ->with([
                    'lease:id,property_id,tenant_id,due_day,status',
                    'lease.tenant:id,name,deleted_at',
                    'lease.property:id,type,street,number,complement,neighborhood,city,state,deleted_at',
                    'receipts' => fn ($query) => $query->orderBy('date')->orderBy('id'),
                ])
                ->withSum('receipts as received_amount', 'amount')
                ->orderBy('due_date')
                ->limit(6)
                ->get(),
            'endingLeases' => Lease::where('account_id', $accountId)
                ->active()
                ->whereDate('end_date', '<=', $today->addDays(60))
                ->with(['tenant:id,name,deleted_at', 'property:id,street,number,deleted_at'])
                ->orderBy('end_date')
                ->limit(5)
                ->get(['id', 'tenant_id', 'property_id', 'end_date', 'amount', 'status']),
            'vacantProperties' => (clone $vacantProperties)
                ->orderBy('street')
                ->limit(5)
                ->get(['id', 'type', 'street', 'number', 'neighborhood', 'city', 'state', 'rent_amount']),
            'vacantPropertiesCount' => $vacantProperties->count(),
        ]);
    }

    /**
     * Get the expected and received amounts of the last six months, oldest first.
     *
     * @param  Closure(): Builder<Payment>  $accountPayments
     * @return list<array{month: string, expected: float, received: float}>
     */
    private function monthlyRevenue(Closure $accountPayments, CarbonImmutable $currentMonth): array
    {
        $firstMonth = $currentMonth->subMonthsNoOverflow(5);

        $paymentsByMonth = $accountPayments()
            ->where('status', '!=', PaymentStatus::Canceled)
            ->whereBetween('due_date', [$firstMonth->toDateString(), $currentMonth->endOfMonth()->toDateString()])
            ->withSum('receipts as received_amount', 'amount')
            ->get(['id', 'amount', 'due_date'])
            ->groupBy(fn (Payment $payment): string => $payment->due_date->format('Y-m'));

        return collect(range(0, 5))
            ->map(function (int $offset) use ($firstMonth, $paymentsByMonth): array {
                $month = $firstMonth->addMonthsNoOverflow($offset);
                $payments = $paymentsByMonth->get($month->format('Y-m'), collect());

                return [
                    'month' => $month->toDateString(),
                    'expected' => round($payments->sum(fn (Payment $payment): float => (float) $payment->amount), 2),
                    'received' => round($payments->sum(fn (Payment $payment): float => (float) $payment->received_amount), 2),
                ];
            })
            ->all();
    }
}
