<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetMonthlyRevenue;
use App\Actions\Expenses\SummarizeExpenses;
use App\Actions\Leases\SyncLeasePayments;
use App\Actions\Payments\SummarizePayments;
use App\Enums\LeaseStatus;
use App\Enums\Permission;
use App\Enums\PropertyStatus;
use App\Models\ContractTemplate;
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
                'charges' => $monthSummary['charges'],
                'properties' => $properties()->count(),
                'rentedProperties' => $properties()->where('status', PropertyStatus::Rented)->count(),
                'activeLeases' => Lease::where('account_id', $accountId)->active()->count(),
                'tenants' => Tenant::where('account_id', $accountId)->count(),
                ...($request->user()->can(Permission::ManageFinance->value) ? [
                    'expensesPaid' => $expensesSummary['paid'],
                    'expensesPending' => $expensesSummary['pending'],
                    'netIncome' => round($monthSummary['received'] + $monthSummary['charges'] - $expensesSummary['paid'], 2),
                ] : []),
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
                ->withDetails()
                ->orderBy('end_date')
                ->limit(5)
                ->get()
                ->each->withAdjustmentInfo(),
            'adjustmentLeases' => Lease::where('account_id', $accountId)
                ->active()
                ->withDetails()
                ->get()
                ->filter(fn (Lease $lease): bool => $lease->canBeAdjusted())
                ->sortBy(fn (Lease $lease): string => (string) $lease->next_adjustment_date)
                ->take(5)
                ->each->withAdjustmentInfo()
                ->values(),
            'vacantProperties' => $properties()
                ->where('status', PropertyStatus::Available)
                ->withDetails()
                ->orderBy('street')
                ->limit(5)
                ->get(),
            'vacantPropertiesCount' => $properties()->where('status', PropertyStatus::Available)->count(),
            'contractTemplates' => ContractTemplate::where('account_id', $accountId)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'is_default']),
        ]);
    }
}
