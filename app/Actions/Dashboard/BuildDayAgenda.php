<?php

namespace App\Actions\Dashboard;

use App\Actions\Payouts\SummarizeOwnerBalances;
use App\Enums\ExpenseStatus;
use App\Enums\LeadInterest;
use App\Enums\LeadSource;
use App\Enums\Permission;
use App\Enums\ProposalStatus;
use App\Enums\VisitStatus;
use App\Models\Deal;
use App\Models\DealProposal;
use App\Models\DealStage;
use App\Models\DealVisit;
use App\Models\Expense;
use App\Models\Lead;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * @phpstan-type AgendaItem array{key: string, count: int, amount: float|null}
 */
class BuildDayAgenda
{
    public function __construct(private SummarizeOwnerBalances $summarizeOwnerBalances) {}

    /**
     * List what needs the user's attention today, as far as their permissions go: payments to
     * collect, payouts to make, expenses to pay, leases to renew or adjust, proposals to answer and
     * the visits of the day, plus the visits and the listings asked through the showcase.
     *
     * @return array{items: list<AgendaItem>, visits: Collection<int, DealVisit>}
     */
    public function handle(User $user): array
    {
        $items = [];

        if ($user->can(Permission::RegisterReceipts->value)) {
            $items[] = $this->paymentsItem('overdue_payments', $this->payments()->overdue());
            $items[] = $this->paymentsItem('payments_due_today', $this->payments()->open()->whereDate('due_date', today()));
        }

        if ($user->can(Permission::ManageFinance->value)) {
            $items[] = $this->payoutsItem();

            $expenses = Expense::query()
                ->where('status', ExpenseStatus::Pending)
                ->whereDate('due_date', '<=', today())
                ->get(['id', 'amount']);
            $items[] = $this->item('expenses_due', $expenses->count(), $expenses->sum(fn (Expense $expense): float => (float) $expense->amount));
        }

        if ($user->can(Permission::ManageRentals->value)) {
            $items[] = $this->item('leases_ending', Lease::query()->endingWithin(30)->count());
            $items[] = $this->item('adjustments_due', Lease::query()
                ->active()
                ->withCount('adjustments')
                ->get(['id', 'start_date', 'end_date', 'status'])
                ->filter(fn (Lease $lease): bool => $lease->canBeAdjusted())
                ->count());
        }

        $visits = new Collection;

        if ($user->can(Permission::ManageSales->value)) {
            [$proposals, $visits] = Owner::ignoringSelection(fn (): array => [
                DealProposal::query()
                    ->where('status', ProposalStatus::Pending)
                    ->whereHas('deal', fn (Builder $query) => $query->whereNull('closed_at'))
                    ->count(),
                DealVisit::query()
                    ->where('status', VisitStatus::Scheduled)
                    ->whereBetween('scheduled_at', [today()->startOfDay(), today()->endOfDay()])
                    ->with([
                        'deal:id,lead_id,property_id',
                        'deal.lead:id,name',
                        'deal.property:id,street,number,neighborhood,deleted_at',
                        'user:id,name',
                    ])
                    ->orderBy('scheduled_at')
                    ->get(['id', 'deal_id', 'user_id', 'scheduled_at']),
            ]);

            $items[] = $this->item('proposals_pending', $proposals);
            $items[] = $this->item('showcase_requests', Owner::ignoringSelection(fn (): int => $this->showcaseRequests()));
            $items[] = $this->item('listing_requests', $this->listingRequests());
        }

        return [
            'items' => array_values(array_filter($items, fn (array $item): bool => $item['count'] > 0)),
            'visits' => $visits,
        ];
    }

    /**
     * Count the visits asked through the showcase that nobody has followed up yet: their deals are still
     * in the first stage of the funnel, with no visit scheduled.
     */
    private function showcaseRequests(): int
    {
        $firstStageId = DealStage::query()
            ->whereNull('outcome')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');

        if ($firstStageId === null) {
            return 0;
        }

        return Deal::query()
            ->where('deal_stage_id', $firstStageId)
            ->whereNull('closed_at')
            ->whereDoesntHave('visits')
            ->whereHas('lead', fn (Builder $query) => $query->where('source', LeadSource::Website))
            ->count();
    }

    /**
     * Count the people who asked to list a property and are not owners of the account yet: once the
     * agency registers them as owners, with the same phone, they leave the agenda.
     */
    private function listingRequests(): int
    {
        return Lead::query()
            ->where('interest', LeadInterest::List)
            ->whereNotExists(fn (QueryBuilder $query) => $query
                ->selectRaw('1')
                ->from('owners')
                ->whereColumn('owners.account_id', 'leads.account_id')
                ->whereColumn('owners.phone', 'leads.phone')
                ->whereNull('owners.deleted_at'))
            ->count();
    }

    /**
     * Count the owners whose payout is due and how much they are owed, among the owners the user sees.
     *
     * @return AgendaItem
     */
    private function payoutsItem(): array
    {
        $ownerId = Owner::selectedId();
        $ownerIds = array_values(Owner::query()
            ->when($ownerId !== null, fn (Builder $query) => $query->whereKey($ownerId))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());

        $due = collect($this->summarizeOwnerBalances->balances($ownerIds))
            ->filter(fn (array $balance): bool => $balance['available'] > 0);

        return $this->item('payouts_available', $due->count(), $due->sum(fn (array $balance): float => $balance['available']));
    }

    /**
     * Count the given payments and what is still to be received of them.
     *
     * @param  Builder<Payment>  $query
     * @return AgendaItem
     */
    private function paymentsItem(string $key, Builder $query): array
    {
        $payments = $query
            ->withSum('receipts as received_amount', 'amount')
            ->get(['id', 'amount']);

        return $this->item($key, $payments->count(), $payments->sum(
            fn (Payment $payment): float => max(0, (float) $payment->amount - (float) $payment->received_amount),
        ));
    }

    /**
     * @return Builder<Payment>
     */
    private function payments(): Builder
    {
        return Payment::query()->whereHas('lease');
    }

    /**
     * @return AgendaItem
     */
    private function item(string $key, int $count, ?float $amount = null): array
    {
        return [
            'key' => $key,
            'count' => $count,
            'amount' => $amount !== null ? round($amount, 2) + 0.0 : null,
        ];
    }
}
