<?php

use App\Actions\Dashboard\BuildDayAgenda;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Enums\ProposalStatus;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Deal;
use App\Models\DealProposal;
use App\Models\DealVisit;
use App\Models\Expense;
use App\Models\Lead;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
});

/**
 * An agency's day: rent overdue and due today, a payout due, an expense to pay, a lease ending,
 * one to adjust, and the deals of two agents with their proposals and visits.
 *
 * @return array{account: Account, branch: Branch, admin: User, bruno: User, ana: User}
 */
function agendaScenario(): array
{
    $account = Account::factory()->agency()->create();
    $branch = Branch::factory()->for($account, 'account')->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $lease = fn (array $attributes) => Lease::factory()
        ->for($account, 'account')
        ->for(Property::factory()->for($account, 'account')->for($owner, 'owner')->state(['branch_id' => $branch->id]))
        ->for(Tenant::factory()->for($account, 'account'))
        ->create(['amount' => 2000, 'due_day' => 28, ...$attributes]);

    $rented = $lease(['start_date' => '2026-01-10', 'end_date' => '2028-07-09', 'due_day' => 15]);
    Payment::factory()->for($rented)->create(['amount' => 1200, 'due_date' => '2026-10-05', 'reference_month' => '2026-09-01']);
    $dueToday = Payment::factory()->for($rented)->create(['amount' => 2000, 'due_date' => '2026-10-15', 'status' => PaymentStatus::Partial]);
    Receipt::factory()->for($dueToday)->create(['amount' => 500]);

    $lease(['start_date' => '2025-11-02', 'end_date' => '2026-11-01']);
    $lease(['start_date' => '2025-10-20', 'end_date' => '2028-10-19']);

    OwnerLedgerEntry::factory()->for($owner)->create(['type' => LedgerEntryType::Rent, 'amount' => 1000, 'available_on' => '2026-10-05']);
    Expense::factory()->for($account, 'account')->for($rented->property)->create(['amount' => 250, 'due_date' => '2026-10-14']);
    Expense::factory()->for($account, 'account')->for($rented->property)->create(['amount' => 90, 'due_date' => '2026-10-30']);

    $bruno = User::factory()->for($account, 'account')->withRole(Role::General)->create(['name' => 'Bruno']);
    $ana = User::factory()->for($account, 'account')->withRole(Role::General)->create(['name' => 'Ana']);

    foreach ([$bruno, $ana] as $agent) {
        $agent->branches()->attach($branch);
        $lead = Lead::factory()->for($account, 'account')->create(['branch_id' => $branch->id]);
        $deal = Deal::factory()->for($account, 'account')->for($lead)->create(['user_id' => $agent->id]);
        DealProposal::factory()->for($deal)->create();
        DealProposal::factory()->for($deal)->create(['status' => ProposalStatus::Accepted]);
        DealVisit::factory()->for($deal)->create(['user_id' => $agent->id, 'scheduled_at' => '2026-10-15 14:00:00']);
        DealVisit::factory()->for($deal)->create(['user_id' => $agent->id, 'scheduled_at' => '2026-10-16 10:00:00']);
    }

    return [
        'account' => $account,
        'branch' => $branch,
        'admin' => User::factory()->for($account, 'account')->create(),
        'bruno' => $bruno,
        'ana' => $ana,
    ];
}

/**
 * @return array<string, array{count: int, amount: float|null}>
 */
function agendaItemsFor(User $user): array
{
    test()->actingAs($user);

    return collect(app(BuildDayAgenda::class)->handle($user)['items'])
        ->mapWithKeys(fn (array $item): array => [$item['key'] => ['count' => $item['count'], 'amount' => $item['amount']]])
        ->all();
}

test('administrators see everything that needs attention today', function () {
    ['admin' => $admin] = agendaScenario();

    expect(agendaItemsFor($admin))->toBe([
        'overdue_payments' => ['count' => 1, 'amount' => 1200.0],
        'payments_due_today' => ['count' => 1, 'amount' => 1500.0],
        'payouts_available' => ['count' => 1, 'amount' => 1000.0],
        'expenses_due' => ['count' => 1, 'amount' => 250.0],
        'leases_ending' => ['count' => 1, 'amount' => null],
        'adjustments_due' => ['count' => 1, 'amount' => null],
        'proposals_pending' => ['count' => 2, 'amount' => null],
    ]);

    expect(app(BuildDayAgenda::class)->handle($admin)['visits'])->toHaveCount(2);
});

test('general members see the money and the proposals and visits of the whole team', function () {
    ['bruno' => $bruno] = agendaScenario();

    $items = agendaItemsFor($bruno);

    expect($items)->toHaveKeys(['payouts_available', 'expenses_due'])
        ->and($items['proposals_pending']['count'])->toBe(2)
        ->and(app(BuildDayAgenda::class)->handle($bruno)['visits'])->toHaveCount(2);
});

test('the agenda is on the agency dashboard only', function () {
    ['admin' => $admin] = agendaScenario();
    $owner = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('agenda.items', 7)
            ->has('agenda.visits', 2)
            ->where('agenda.visits.0.deal.lead.name', fn (string $name) => $name !== '')
        );

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('agenda', null));
});
