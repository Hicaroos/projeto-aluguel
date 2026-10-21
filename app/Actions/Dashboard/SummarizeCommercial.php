<?php

namespace App\Actions\Dashboard;

use App\Enums\DealOutcome;
use App\Enums\Permission;
use App\Enums\ProposalStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\Deal;
use App\Models\DealProposal;
use App\Models\DealVisit;
use App\Models\Lead;
use App\Models\Owner;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @phpstan-type TeamMember array{id: int, name: string, leads: int, visits: int, proposals: int, won: int}
 * @phpstan-type Commercial array{
 *     newLeads: int,
 *     visitsDone: int,
 *     pendingProposals: int,
 *     wonDeals: int,
 *     openDeals: int,
 *     sales: array{count: int, total: float, commission: float},
 *     upcomingVisits: Collection<int, DealVisit>,
 *     team: list<TeamMember>|null,
 * }
 */
class SummarizeCommercial
{
    /**
     * Summarize the month of the funnel: new leads, visits made, proposals waiting for an answer, deals
     * won and sales, plus the visits of the coming week. Agents see only their own work; admins and
     * general staff see the whole team, with each member's figures. Null for those who don't handle
     * sales, and until the account uses the funnel.
     *
     * @return Commercial|null
     */
    public function handle(User $user, CarbonImmutable $month): ?array
    {
        if (! $user->can(Permission::ManageSales->value)) {
            return null;
        }

        return Owner::ignoringSelection(fn (): ?array => $this->summarize($user, $month));
    }

    /**
     * @return Commercial|null
     */
    private function summarize(User $user, CarbonImmutable $month): ?array
    {
        if (! Lead::query()->exists() && ! Deal::query()->exists() && ! Sale::query()->exists()) {
            return null;
        }

        $agentId = $user->role === Role::Agent ? $user->id : null;
        $period = [$month->startOfMonth()->startOfDay(), $month->endOfMonth()->endOfDay()];
        $days = [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()];

        $leads = Lead::query()
            ->whereBetween('created_at', $period)
            ->when($agentId, fn (Builder $query, int $agentId) => $query->where('user_id', $agentId))
            ->get(['id', 'user_id']);
        $visits = DealVisit::query()
            ->where('status', VisitStatus::Done)
            ->whereBetween('scheduled_at', $period)
            ->when($agentId, $this->visitsOf(...))
            ->with('deal:id,user_id')
            ->get(['id', 'deal_id', 'user_id']);
        $won = Deal::query()
            ->whereBetween('closed_at', $period)
            ->whereHas('stage', fn (Builder $query) => $query->where('outcome', DealOutcome::Won))
            ->when($agentId, fn (Builder $query, int $agentId) => $query->where('user_id', $agentId))
            ->get(['id', 'user_id']);
        $sales = Sale::query()
            ->soldInMonth($month)
            ->when($agentId, fn (Builder $query, int $agentId) => $query->where('user_id', $agentId))
            ->get(['id', 'user_id', 'price', 'commission_amount']);

        return [
            'newLeads' => $leads->count(),
            'visitsDone' => $visits->count(),
            'pendingProposals' => DealProposal::query()
                ->where('status', ProposalStatus::Pending)
                ->whereHas('deal', fn (Builder $query) => $query
                    ->whereNull('closed_at')
                    ->when($agentId, fn (Builder $query, int $agentId) => $query->where('user_id', $agentId)))
                ->count(),
            'wonDeals' => $won->count(),
            'openDeals' => Deal::query()
                ->whereNull('closed_at')
                ->when($agentId, fn (Builder $query, int $agentId) => $query->where('user_id', $agentId))
                ->count(),
            'sales' => [
                'count' => $sales->count(),
                'total' => round($sales->sum(fn (Sale $sale): float => (float) $sale->price), 2),
                'commission' => round($sales->sum(fn (Sale $sale): float => (float) $sale->commission_amount), 2),
            ],
            'upcomingVisits' => $this->upcomingVisits($agentId),
            'team' => $agentId === null ? $this->team($user, $leads, $visits, $won, $days) : null,
        ];
    }

    /**
     * Get each team member's figures for the month: the agents always, and anyone else who did
     * something in the funnel. Those who closed the most come first.
     *
     * @param  Collection<int, Lead>  $leads
     * @param  Collection<int, DealVisit>  $visits
     * @param  Collection<int, Deal>  $won
     * @param  array{string, string}  $days
     * @return list<TeamMember>
     */
    private function team(User $user, Collection $leads, Collection $visits, Collection $won, array $days): array
    {
        $proposals = DealProposal::query()
            ->whereBetween('proposed_on', $days)
            ->with('deal:id,user_id')
            ->get(['id', 'deal_id']);

        $counts = [
            'leads' => $leads->countBy(fn (Lead $lead): int => $lead->user_id ?? 0)->all(),
            'visits' => $visits->countBy(fn (DealVisit $visit): int => $this->visitAgent($visit) ?? 0)->all(),
            'proposals' => $proposals->countBy(fn (DealProposal $proposal): int => $proposal->deal->user_id ?? 0)->all(),
            'won' => $won->countBy(fn (Deal $deal): int => $deal->user_id ?? 0)->all(),
        ];

        $activeIds = collect($counts)->flatMap(fn (array $byUser): array => array_keys($byUser))->filter()->unique();

        $members = User::query()
            ->where('account_id', $user->account_id)
            ->whereNull('deactivated_at')
            ->where(fn (Builder $query) => $query->where('role', Role::Agent)->orWhereIn('id', $activeIds))
            ->get(['id', 'name'])
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'leads' => $counts['leads'][$member->id] ?? 0,
                'visits' => $counts['visits'][$member->id] ?? 0,
                'proposals' => $counts['proposals'][$member->id] ?? 0,
                'won' => $counts['won'][$member->id] ?? 0,
            ])
            ->sortBy([
                ['won', 'desc'],
                ['proposals', 'desc'],
                ['visits', 'desc'],
                ['leads', 'desc'],
                ['name', 'asc'],
            ]);

        return array_values($members->all());
    }

    /**
     * Get the scheduled visits of the coming week, only the agent's own for agents.
     *
     * @return Collection<int, DealVisit>
     */
    private function upcomingVisits(?int $agentId): Collection
    {
        return DealVisit::query()
            ->where('status', VisitStatus::Scheduled)
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->when($agentId, $this->visitsOf(...))
            ->with([
                'deal:id,lead_id,property_id',
                'deal.lead:id,name',
                'deal.property:id,street,number,neighborhood,deleted_at',
                'user:id,name',
            ])
            ->orderBy('scheduled_at')
            ->limit(6)
            ->get();
    }

    /**
     * Whose visit it is: the member who makes it or, when nobody was assigned, the one in charge of the deal.
     */
    private function visitAgent(DealVisit $visit): ?int
    {
        return $visit->user_id ?? $visit->deal->user_id;
    }

    /**
     * Limit the visits to the agent's: those they make, and those of their deals nobody was assigned to.
     *
     * @param  Builder<DealVisit>  $query
     */
    private function visitsOf(Builder $query, int $agentId): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('user_id', $agentId)
            ->orWhere(fn (Builder $query) => $query
                ->whereNull('user_id')
                ->whereHas('deal', fn (Builder $query) => $query->where('user_id', $agentId))));
    }
}
