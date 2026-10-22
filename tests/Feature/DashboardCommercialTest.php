<?php

use App\Enums\ProposalStatus;
use App\Enums\Role;
use App\Enums\VisitStatus;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Deal;
use App\Models\DealProposal;
use App\Models\DealStage;
use App\Models\DealVisit;
use App\Models\Lead;
use App\Models\Property;
use App\Models\Sale;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
});

/**
 * An agency's month in the funnel: Bruno works an open deal, Ana won one and sold the property,
 * Carla did nothing yet, and Davi left the team.
 *
 * @return array{account: Account, branch: Branch, admin: User, bruno: User, ana: User, carla: User}
 */
function commercialScenario(): array
{
    $account = Account::factory()->agency()->create();
    $branch = Branch::factory()->for($account, 'account')->create();
    $agent = function (string $name, array $attributes = []) use ($account, $branch): User {
        $agent = User::factory()->for($account, 'account')->withRole(Role::General)->create(['name' => $name, ...$attributes]);
        $agent->branches()->attach($branch);

        return $agent;
    };
    $lead = fn (?User $agent, string $createdAt = '2026-10-10 10:00:00') => Lead::factory()->for($account, 'account')->create([
        'branch_id' => $branch->id,
        'user_id' => $agent?->id,
        'created_at' => $createdAt,
    ]);

    $bruno = $agent('Bruno');
    $ana = $agent('Ana');
    $carla = $agent('Carla');
    $agent('Davi', ['deactivated_at' => '2026-09-01 10:00:00']);

    $brunoLead = $lead($bruno);
    $lead($bruno);
    $lead($bruno, '2026-09-20 10:00:00');
    $anaLead = $lead($ana, '2026-09-25 10:00:00');
    $lead($ana);
    $lead(null);

    $open = DealStage::factory()->for($account, 'account')->create();
    $won = DealStage::factory()->for($account, 'account')->won()->create();

    $brunoDeal = Deal::factory()->for($account, 'account')->for($brunoLead)->create(['deal_stage_id' => $open->id, 'user_id' => $bruno->id]);
    DealProposal::factory()->for($brunoDeal)->create(['proposed_on' => '2026-10-14']);
    DealVisit::factory()->for($brunoDeal)->create(['user_id' => $bruno->id, 'status' => VisitStatus::Done, 'scheduled_at' => '2026-10-10 10:00:00']);
    DealVisit::factory()->for($brunoDeal)->create(['user_id' => $bruno->id, 'status' => VisitStatus::Scheduled, 'scheduled_at' => '2026-10-17 15:00:00']);

    $anaDeal = Deal::factory()->for($account, 'account')->for($anaLead)->create(['deal_stage_id' => $won->id, 'user_id' => $ana->id, 'closed_at' => '2026-10-12 16:00:00']);
    DealProposal::factory()->for($anaDeal)->create(['proposed_on' => '2026-10-03', 'status' => ProposalStatus::Accepted]);
    DealVisit::factory()->for($anaDeal)->create(['user_id' => null, 'status' => VisitStatus::Done, 'scheduled_at' => '2026-10-05 09:00:00']);
    DealVisit::factory()->for($anaDeal)->create(['user_id' => $ana->id, 'status' => VisitStatus::Canceled, 'scheduled_at' => '2026-10-06 09:00:00']);
    Sale::factory()->for($account, 'account')->for(Property::factory()->for($account, 'account')->state(['branch_id' => $branch->id]))->create(['deal_id' => $anaDeal->id, 'user_id' => $ana->id, 'price' => 300000, 'commission_amount' => 15000, 'sold_on' => '2026-10-12']);

    return [
        'account' => $account,
        'branch' => $branch,
        'admin' => User::factory()->for($account, 'account')->create(),
        'bruno' => $bruno,
        'ana' => $ana,
        'carla' => $carla,
    ];
}

test('admins see the month of the whole team, member by member', function () {
    ['admin' => $admin, 'bruno' => $bruno, 'ana' => $ana, 'carla' => $carla] = commercialScenario();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('commercial.newLeads', 4)
            ->where('commercial.visitsDone', 2)
            ->where('commercial.pendingProposals', 1)
            ->where('commercial.wonDeals', 1)
            ->where('commercial.openDeals', 1)
            ->where('commercial.sales', ['count' => 1, 'total' => 300000, 'commission' => 15000])
            ->has('commercial.upcomingVisits', 1)
            ->where('commercial.team', [
                ['id' => $ana->id, 'name' => 'Ana', 'leads' => 1, 'visits' => 1, 'proposals' => 1, 'won' => 1],
                ['id' => $bruno->id, 'name' => 'Bruno', 'leads' => 2, 'visits' => 1, 'proposals' => 1, 'won' => 0],
                ['id' => $carla->id, 'name' => 'Carla', 'leads' => 0, 'visits' => 0, 'proposals' => 0, 'won' => 0],
            ])
        );
});

test('general members see the month of the whole team too', function () {
    ['bruno' => $bruno] = commercialScenario();

    $this->actingAs($bruno)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('commercial.newLeads', 4)
            ->where('commercial.wonDeals', 1)
            ->has('commercial.team', 3)
        );
});

test('the commercial block is left out before the funnel is used', function () {
    $newcomer = User::factory()->for(Account::factory()->agency(), 'account')->create();

    $this->actingAs($newcomer)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('commercial', null));
});
