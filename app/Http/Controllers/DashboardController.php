<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetMonthlyRevenue;
use App\Actions\Expenses\SummarizeExpenses;
use App\Actions\Leases\SyncLeasePayments;
use App\Actions\Payments\SummarizePayments;
use App\Enums\ContractTemplateKind;
use App\Enums\LeaseStatus;
use App\Enums\Permission;
use App\Enums\PropertyStatus;
use App\Enums\VisitStatus;
use App\Models\ContractTemplate;
use App\Models\Deal;
use App\Models\DealVisit;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Sale;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
        $properties = fn (): Builder => Property::where('account_id', $accountId)->rentable();

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
                'tenants' => Tenant::where('account_id', $accountId)
                    ->when(Owner::selectedId(), fn (Builder $query, int $ownerId) => Tenant::restrictToOwner($query, $ownerId))
                    ->count(),
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
                ->where('kind', ContractTemplateKind::Lease)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'is_default']),
            'sales' => Owner::ignoringSelection(fn (): ?array => $this->salesOverview($month)),
        ]);
    }

    /**
     * Summarize the sales of the month, the open deals and the visits of the coming week, once
     * the account uses the funnel.
     *
     * @return array{count: int, total: float, commission: float, openDeals: int, upcomingVisits: Collection<int, DealVisit>}|null
     */
    private function salesOverview(CarbonImmutable $month): ?array
    {
        if (! Deal::query()->exists() && ! Sale::query()->exists()) {
            return null;
        }

        $sales = Sale::query()->soldInMonth($month)->get(['id', 'price', 'commission_amount']);

        return [
            'count' => $sales->count(),
            'total' => round($sales->sum(fn (Sale $sale): float => (float) $sale->price), 2),
            'commission' => round($sales->sum(fn (Sale $sale): float => (float) $sale->commission_amount), 2),
            'openDeals' => Deal::query()->whereNull('closed_at')->count(),
            'upcomingVisits' => DealVisit::query()
                ->where('status', VisitStatus::Scheduled)
                ->whereBetween('scheduled_at', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
                ->with([
                    'deal:id,lead_id,property_id',
                    'deal.lead:id,name',
                    'deal.property:id,street,number,neighborhood,deleted_at',
                    'user:id,name',
                ])
                ->orderBy('scheduled_at')
                ->limit(6)
                ->get(),
        ];
    }
}
