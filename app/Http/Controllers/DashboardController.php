<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetMonthlyRevenue;
use App\Actions\Expenses\SummarizeExpenses;
use App\Actions\Leases\SyncLeasePayments;
use App\Actions\Payments\SummarizePayments;
use App\Enums\LeaseStatus;
use App\Enums\PropertyStatus;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the account's overview: month figures, revenue trend and items that need attention.
     */
    public function __invoke(
        Request $request,
        SyncLeasePayments $syncLeasePayments,
        SummarizePayments $summarizePayments,
        SummarizeExpenses $summarizeExpenses,
        GetMonthlyRevenue $getMonthlyRevenue,
    ): Response {
        $accountId = $request->user()->account_id;

        if ($accountId !== null) {
            $syncLeasePayments->handleMissingForAccount($accountId);
        }

        $month = CarbonImmutable::today()->startOfMonth();
        $accountPayments = fn (): Builder => Payment::where('account_id', $accountId)->whereHas('lease');
        $monthSummary = $summarizePayments->handle($accountPayments()->dueInMonth($month));
        $overdueSummary = $summarizePayments->handle($accountPayments()->overdue());
        $expensesSummary = $summarizeExpenses->handle(Expense::where('account_id', $accountId)->dueInMonth($month));
        $properties = fn (): Builder => Property::where('account_id', $accountId);

        return Inertia::render('Dashboard', [
            'month' => $month->toDateString(),
            'stats' => [
                'expected' => $monthSummary['expected'],
                'received' => $monthSummary['received'],
                'overdue' => $overdueSummary['overdue'],
                'overdueCount' => $overdueSummary['overdue_count'],
                'expensesPaid' => $expensesSummary['paid'],
                'expensesPending' => $expensesSummary['pending'],
                'charges' => $monthSummary['charges'],
                'netIncome' => round($monthSummary['received'] + $monthSummary['charges'] - $expensesSummary['paid'], 2),
                'properties' => $properties()->count(),
                'rentedProperties' => $properties()->where('status', PropertyStatus::Rented)->count(),
                'activeLeases' => Lease::where('account_id', $accountId)->active()->count(),
                'tenants' => Tenant::where('account_id', $accountId)->count(),
            ],
            'monthlyRevenue' => $getMonthlyRevenue->handle($accountId, $month),
            'attentionPayments' => $accountPayments()
                ->open()
                ->whereHas('lease', fn (Builder $query) => $query->where('status', LeaseStatus::Active))
                ->whereDate('due_date', '<=', today()->addDays(7))
                ->withListDetails()
                ->orderBy('due_date')
                ->limit(6)
                ->get(),
            'formerTenantDebts' => $accountPayments()
                ->overdue()
                ->whereHas('lease', fn (Builder $query) => $query->where('status', '!=', LeaseStatus::Active))
                ->withListDetails()
                ->orderBy('due_date')
                ->limit(5)
                ->get(),
            'endingLeases' => Lease::where('account_id', $accountId)
                ->endingWithin(60)
                ->with(['tenant:id,name,deleted_at', 'property:id,street,number,deleted_at'])
                ->orderBy('end_date')
                ->limit(5)
                ->get(['id', 'tenant_id', 'property_id', 'end_date', 'amount', 'status']),
            'adjustmentLeases' => Lease::where('account_id', $accountId)
                ->active()
                ->with(['tenant:id,name,deleted_at', 'property:id,street,number,deleted_at'])
                ->withCount('adjustments')
                ->get(['id', 'tenant_id', 'property_id', 'start_date', 'end_date', 'amount', 'adjustment_index', 'status'])
                ->filter(fn (Lease $lease): bool => $lease->canBeAdjusted())
                ->sortBy(fn (Lease $lease): string => (string) $lease->next_adjustment_date)
                ->take(5)
                ->map(fn (Lease $lease): Lease => $lease->append(['next_adjustment_date', 'adjustment_status']))
                ->values(),
            'vacantProperties' => $properties()
                ->where('status', PropertyStatus::Available)
                ->orderBy('street')
                ->limit(5)
                ->get(['id', 'type', 'street', 'number', 'neighborhood', 'city', 'state', 'rent_amount']),
            'vacantPropertiesCount' => $properties()->where('status', PropertyStatus::Available)->count(),
        ]);
    }
}
