<?php

use App\Enums\DealOutcome;
use App\Enums\DealType;
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
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
});

/**
 * A single owner account with the default funnel, a lead and a property for sale.
 *
 * @return array{account: Account, user: User, lead: Lead, property: Property, stages: Collection<int, DealStage>}
 */
function pipelineScenario(): array
{
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();

    foreach (DealStage::DEFAULTS as $order => $stage) {
        DealStage::factory()->for($account, 'account')->create([...$stage, 'sort_order' => $order]);
    }

    return [
        'account' => $account,
        'user' => $user,
        'lead' => Lead::factory()->for($account, 'account')->create(['name' => 'Carlos Comprador']),
        'property' => Property::factory()->for($account, 'account')->create([
            'for_sale' => true,
            'sale_price' => 300000,
        ]),
        'stages' => DealStage::withoutGlobalScopes()->where('account_id', $account->id)->inFunnelOrder()->get()->values(),
    ];
}

/**
 * @return array<string, mixed>
 */
function leadPayload(array $overrides = []): array
{
    return [
        'name' => 'Marina Souza',
        'phone' => '(87) 99999-1111',
        'email' => 'marina@example.com',
        'source' => 'instagram',
        'interest' => 'buy',
        'property_type' => 'house',
        'max_price' => '350000',
        'neighborhoods' => 'Centro',
        'payment_plan' => 'financing',
        'notes' => 'Quer 3 quartos.',
        ...$overrides,
    ];
}

test('leads are listed, registered and updated', function () {
    ['user' => $user, 'lead' => $lead, 'account' => $account] = pipelineScenario();
    Lead::factory()->create();

    $this->actingAs($user)
        ->get(route('leads.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('leads/Index')
            ->has('leads.data', 1)
            ->where('leads.data.0.id', $lead->id)
            ->has('properties', 1)
        );

    $this->actingAs($user)
        ->post(route('leads.store'), leadPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('leads.index'));

    $this->assertDatabaseHas('leads', [
        'account_id' => $account->id,
        'name' => 'Marina Souza',
        'phone' => '87999991111',
        'interest' => 'buy',
        'payment_plan' => 'financing',
        'user_id' => null,
    ]);

    $this->actingAs($user)
        ->put(route('leads.update', $lead), leadPayload(['name' => 'Carlos Silva', 'interest' => 'rent']))
        ->assertSessionHasNoErrors();

    expect($lead->refresh()->name)->toBe('Carlos Silva');
});

test('leads need a name and what they are looking for', function () {
    ['user' => $user] = pipelineScenario();

    $this->actingAs($user)
        ->post(route('leads.store'), leadPayload(['name' => '', 'interest' => 'steal', 'phone' => '123']))
        ->assertSessionHasErrors(['name', 'interest', 'phone']);
});

test('agency leads belong to the selected branch and to whoever registered them', function () {
    $account = Account::factory()->agency()->create();
    $admin = User::factory()->for($account, 'account')->create();
    $branch = Branch::factory()->for($account, 'account')->create();

    $this->actingAs($admin)
        ->withSession([User::SELECTED_BRANCH_SESSION_KEY => $branch->id])
        ->post(route('leads.store'), leadPayload())
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('leads', [
        'account_id' => $account->id,
        'branch_id' => $branch->id,
        'user_id' => $admin->id,
    ]);
});

test('members only see the leads of their branches', function () {
    $account = Account::factory()->agency()->create();
    $ouricuri = Branch::factory()->for($account, 'account')->create();
    $araripina = Branch::factory()->for($account, 'account')->create();
    $agent = User::factory()->for($account, 'account')->withRole(Role::Agent)->create();
    $agent->branches()->attach($ouricuri);

    $mine = Lead::factory()->for($account, 'account')->create(['branch_id' => $ouricuri->id]);
    $shared = Lead::factory()->for($account, 'account')->create(['branch_id' => null]);
    Lead::factory()->for($account, 'account')->create(['branch_id' => $araripina->id]);

    $this->actingAs($agent)
        ->get(route('leads.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('leads.data', 2)
            ->where('leads.data', fn ($leads) => collect($leads)->pluck('id')->sort()->values()->all() === collect([$mine->id, $shared->id])->sort()->values()->all())
        );
});

test('finance members look at the funnel without changing it', function () {
    $account = Account::factory()->agency()->create();
    $finance = User::factory()->for($account, 'account')->withRole(Role::Finance)->create();

    $this->actingAs($finance)->get(route('leads.index'))->assertOk();
    $this->actingAs($finance)->get(route('deals.index'))->assertOk();
    $this->actingAs($finance)->get(route('sales.index'))->assertOk();
    $this->actingAs($finance)->post(route('leads.store'), leadPayload())->assertForbidden();
});

test('the funnel starts with the default stages', function () {
    $user = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($user)
        ->get(route('deals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('deals/Index')
            ->has('stages', 6)
            ->where('stages.0.name', 'Novo contato')
            ->where('stages.4.outcome', 'won')
            ->where('stages.5.outcome', 'lost')
            ->where('saleCommissionPercent', null)
        );
});

test('deals start in the first stage, worth the price of the property', function () {
    ['user' => $user, 'lead' => $lead, 'property' => $property, 'stages' => $stages] = pipelineScenario();

    $this->actingAs($user)
        ->post(route('deals.store'), ['lead_id' => $lead->id, 'property_id' => $property->id, 'type' => 'sale'])
        ->assertSessionHasNoErrors();

    $deal = Deal::where('lead_id', $lead->id)->sole();

    expect($deal->deal_stage_id)->toBe($stages[0]->id)
        ->and($deal->value)->toBe('300000.00')
        ->and($deal->type)->toBe(DealType::Sale);

    $this->actingAs($user)
        ->get(route('deals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('stages.0.deals', 1)
            ->where('stages.0.deals.0.lead.name', 'Carlos Comprador')
        );
});

test('deals only take properties offered for what they are about', function () {
    ['user' => $user, 'lead' => $lead, 'account' => $account] = pipelineScenario();
    $rentOnly = Property::factory()->for($account, 'account')->create(['for_sale' => false]);

    $this->actingAs($user)
        ->post(route('deals.store'), ['lead_id' => $lead->id, 'property_id' => $rentOnly->id, 'type' => 'sale'])
        ->assertSessionHasErrors(['property_id' => 'Escolha um imóvel disponível para esta negociação.']);

    $this->actingAs($user)
        ->post(route('deals.store'), ['lead_id' => $lead->id, 'property_id' => $rentOnly->id, 'type' => 'rent'])
        ->assertSessionHasNoErrors();

    expect(Deal::where('lead_id', $lead->id)->sole()->value)->toBe($rentOnly->rent_amount);
});

test('deals move between stages, keeping the order of each stage', function () {
    ['user' => $user, 'account' => $account, 'lead' => $lead, 'stages' => $stages] = pipelineScenario();
    $first = Deal::factory()->for($account, 'account')->for($lead, 'lead')->create(['deal_stage_id' => $stages[1]->id, 'position' => 0]);
    $second = Deal::factory()->for($account, 'account')->for($lead, 'lead')->create(['deal_stage_id' => $stages[1]->id, 'position' => 1]);
    $moving = Deal::factory()->for($account, 'account')->for($lead, 'lead')->create(['deal_stage_id' => $stages[0]->id]);

    $this->actingAs($user)
        ->patch(route('deals.move', $moving), ['deal_stage_id' => $stages[1]->id, 'position' => 1])
        ->assertSessionHasNoErrors();

    expect($moving->refresh()->deal_stage_id)->toBe($stages[1]->id)
        ->and($first->refresh()->position)->toBe(0)
        ->and($moving->position)->toBe(1)
        ->and($second->refresh()->position)->toBe(2);
});

test('lost deals need a reason and are closed until they come back to the funnel', function () {
    ['user' => $user, 'account' => $account, 'lead' => $lead, 'stages' => $stages] = pipelineScenario();
    $deal = Deal::factory()->for($account, 'account')->for($lead, 'lead')->create(['deal_stage_id' => $stages[0]->id]);
    $lostStage = $stages->firstWhere('outcome', DealOutcome::Lost);

    $this->actingAs($user)
        ->patch(route('deals.move', $deal), ['deal_stage_id' => $lostStage->id])
        ->assertSessionHasErrors(['lost_reason' => 'Conte por que a negociação foi perdida.']);

    $this->actingAs($user)
        ->patch(route('deals.move', $deal), ['deal_stage_id' => $lostStage->id, 'lost_reason' => 'Achou caro'])
        ->assertSessionHasNoErrors();

    expect($deal->refresh()->closed_at)->not->toBeNull()
        ->and($deal->lost_reason)->toBe('Achou caro');

    $this->actingAs($user)->patch(route('deals.move', $deal), ['deal_stage_id' => $stages[0]->id]);

    expect($deal->refresh()->closed_at)->toBeNull()
        ->and($deal->lost_reason)->toBeNull();
});

test('sales are closed by registering the sale, while rentals close right away', function () {
    ['user' => $user, 'account' => $account, 'lead' => $lead, 'stages' => $stages] = pipelineScenario();
    $wonStage = $stages->firstWhere('outcome', DealOutcome::Won);
    $sale = Deal::factory()->for($account, 'account')->for($lead, 'lead')->create(['deal_stage_id' => $stages[0]->id]);
    $rent = Deal::factory()->for($account, 'account')->for($lead, 'lead')->create(['deal_stage_id' => $stages[0]->id, 'type' => DealType::Rent]);

    $this->actingAs($user)
        ->patch(route('deals.move', $sale), ['deal_stage_id' => $wonStage->id])
        ->assertSessionHasErrors(['deal_stage_id' => 'Registre a venda para fechar esta negociação.']);

    $this->actingAs($user)
        ->patch(route('deals.move', $rent), ['deal_stage_id' => $wonStage->id])
        ->assertSessionHasNoErrors();

    expect($rent->refresh()->closed_at)->not->toBeNull();
});

test('each account shapes its own funnel', function () {
    ['user' => $user, 'account' => $account, 'lead' => $lead, 'stages' => $stages] = pipelineScenario();
    Deal::factory()->for($account, 'account')->for($lead, 'lead')->create(['deal_stage_id' => $stages[1]->id]);

    $this->actingAs($user)
        ->put(route('deal-stages.update'), [
            'stages' => [
                ['id' => $stages[1]->id, 'name' => 'Visita agendada'],
                ['id' => $stages[0]->id, 'name' => 'Primeiro contato'],
                ['id' => null, 'name' => 'Análise de crédito'],
            ],
            'won_name' => 'Vendido',
            'lost_name' => 'Desistiu',
        ])
        ->assertSessionHasNoErrors();

    $names = DealStage::withoutGlobalScopes()->where('account_id', $account->id)->inFunnelOrder()->pluck('name')->all();

    expect($names)->toBe(['Visita agendada', 'Primeiro contato', 'Análise de crédito', 'Vendido', 'Desistiu']);
});

test('stages still holding deals cannot be removed', function () {
    ['user' => $user, 'account' => $account, 'lead' => $lead, 'stages' => $stages] = pipelineScenario();
    Deal::factory()->for($account, 'account')->for($lead, 'lead')->create(['deal_stage_id' => $stages[1]->id]);

    $this->actingAs($user)
        ->put(route('deal-stages.update'), [
            'stages' => [['id' => $stages[0]->id, 'name' => 'Novo contato']],
            'won_name' => 'Fechado',
            'lost_name' => 'Perdido',
        ])
        ->assertSessionHasErrors(['stages' => 'Mova as negociações de uma etapa antes de removê-la.']);

    $this->assertModelExists($stages[1]);
});

test('visits are scheduled and told how they went', function () {
    ['user' => $user, 'account' => $account, 'lead' => $lead, 'stages' => $stages] = pipelineScenario();
    $deal = Deal::factory()->for($account, 'account')->for($lead, 'lead')->create(['deal_stage_id' => $stages[0]->id]);

    $this->actingAs($user)
        ->post(route('deals.visits.store', $deal), ['scheduled_at' => '2026-10-17T14:30'])
        ->assertSessionHasNoErrors();

    $visit = DealVisit::where('deal_id', $deal->id)->sole();

    expect($visit->scheduled_at->format('Y-m-d H:i'))->toBe('2026-10-17 14:30')
        ->and($visit->status)->toBe(VisitStatus::Scheduled);

    $this->actingAs($user)
        ->put(route('deal-visits.update', $visit), [
            'scheduled_at' => '2026-10-17T14:30',
            'status' => 'done',
            'feedback' => 'Gostou da casa.',
        ])
        ->assertSessionHasNoErrors();

    expect($visit->refresh()->status)->toBe(VisitStatus::Done)
        ->and($visit->feedback)->toBe('Gostou da casa.');
});

test('accepting a proposal sets the value of the deal', function () {
    ['user' => $user, 'account' => $account, 'lead' => $lead, 'stages' => $stages] = pipelineScenario();
    $deal = Deal::factory()->for($account, 'account')->for($lead, 'lead')->create(['deal_stage_id' => $stages[0]->id, 'value' => 300000]);

    $this->actingAs($user)
        ->post(route('deals.proposals.store', $deal), [
            'amount' => '280000',
            'payment_plan' => 'financing',
            'conditions' => 'Entrada de 20%',
            'proposed_on' => '2026-10-15',
        ])
        ->assertSessionHasNoErrors();

    $proposal = DealProposal::where('deal_id', $deal->id)->sole();

    $this->actingAs($user)
        ->put(route('deal-proposals.update', $proposal), [
            'amount' => '280000',
            'proposed_on' => '2026-10-15',
            'status' => 'accepted',
        ])
        ->assertSessionHasNoErrors();

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Accepted)
        ->and($deal->refresh()->value)->toBe('280000.00');
});

test('deals of other accounts are out of reach', function () {
    ['user' => $user] = pipelineScenario();
    $stranger = Deal::factory()->create();

    $this->actingAs($user)->delete(route('deals.destroy', $stranger))->assertNotFound();
    $this->actingAs($user)->post(route('deals.visits.store', $stranger), ['scheduled_at' => '2026-10-17T14:30'])->assertNotFound();
});

test('removing a lead removes their deals', function () {
    ['user' => $user, 'account' => $account, 'lead' => $lead, 'stages' => $stages] = pipelineScenario();
    $deal = Deal::factory()->for($account, 'account')->for($lead, 'lead')->create(['deal_stage_id' => $stages[0]->id]);

    $this->actingAs($user)->delete(route('leads.destroy', $lead))->assertRedirect(route('leads.index'));

    $this->assertModelMissing($lead);
    $this->assertModelMissing($deal);
});
