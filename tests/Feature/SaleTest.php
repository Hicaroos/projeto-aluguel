<?php

use App\Enums\DealOutcome;
use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Enums\PropertyStatus;
use App\Enums\RentalOutcome;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Lead;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
});

/**
 * An account with the default funnel and an open deal for a property for sale.
 *
 * @return array{account: Account, user: User, owner: Owner, property: Property, deal: Deal, stages: Collection<int, DealStage>}
 */
function saleScenario(bool $agency = false): array
{
    $account = $agency ? Account::factory()->agency()->create() : Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();
    $owner = Owner::factory()->for($account, 'account')->create();

    foreach (DealStage::DEFAULTS as $order => $stage) {
        DealStage::factory()->for($account, 'account')->create([...$stage, 'sort_order' => $order]);
    }

    $stages = DealStage::withoutGlobalScopes()->where('account_id', $account->id)->inFunnelOrder()->get()->values();

    $property = Property::factory()->for($account, 'account')->for($owner, 'owner')->create([
        'branch_id' => $agency ? Branch::factory()->for($account, 'account')->create()->id : null,
        'for_sale' => true,
        'sale_price' => 300000,
    ]);

    return [
        'account' => $account,
        'user' => $user,
        'owner' => $owner,
        'property' => $property,
        'deal' => Deal::factory()->for($account, 'account')->create([
            'lead_id' => Lead::factory()->for($account, 'account')->create(['name' => 'Carlos Comprador'])->id,
            'property_id' => $property->id,
            'deal_stage_id' => $stages[2]->id,
        ]),
        'stages' => $stages,
    ];
}

/**
 * @return array<string, mixed>
 */
function salePayload(array $overrides = []): array
{
    return [
        'buyer_name' => 'Carlos Comprador',
        'buyer_document' => '111.222.333-44',
        'buyer_phone' => '(87) 98888-7777',
        'price' => '290000',
        'sold_on' => '2026-10-14',
        'payment_plan' => 'financing',
        ...$overrides,
    ];
}

test('registering a sale takes the property out of the portfolio and closes the deal', function () {
    ['user' => $user, 'account' => $account, 'owner' => $owner, 'property' => $property, 'deal' => $deal, 'stages' => $stages] = saleScenario();
    $competingDeal = Deal::factory()->for($account, 'account')->create(['property_id' => $property->id, 'deal_stage_id' => $stages[0]->id]);

    $this->actingAs($user)
        ->post(route('deals.sale.store', $deal), salePayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('deals.index', ['show' => $deal->id]));

    $sale = Sale::where('deal_id', $deal->id)->sole();

    expect($sale->price)->toBe('290000.00')
        ->and($sale->buyer_document)->toBe('11122233344')
        ->and($sale->seller_owner_id)->toBe($owner->id)
        ->and($sale->commission_percent)->toBeNull()
        ->and($sale->commission_amount)->toBeNull();

    $property->refresh();

    expect($property->status)->toBe(PropertyStatus::Sold)
        ->and($property->for_sale)->toBeFalse()
        ->and($property->for_rent)->toBeFalse();

    $deal->refresh();

    expect($deal->stage->outcome)->toBe(DealOutcome::Won)
        ->and($deal->closed_at)->not->toBeNull()
        ->and($deal->value)->toBe('290000.00');

    $competingDeal->refresh();

    expect($competingDeal->stage->outcome)->toBe(DealOutcome::Lost)
        ->and($competingDeal->lost_reason)->toBe('Imóvel vendido para outro comprador');
});

test('agencies earn the commission on their sales', function () {
    ['user' => $user, 'deal' => $deal] = saleScenario(agency: true);

    $this->actingAs($user)
        ->post(route('deals.sale.store', $deal), salePayload(['commission_percent' => '6', 'user_id' => $user->id]))
        ->assertSessionHasNoErrors();

    $sale = Sale::where('deal_id', $deal->id)->sole();

    expect($sale->commission_percent)->toBe('6.00')
        ->and($sale->commission_amount)->toBe('17400.00')
        ->and($sale->user_id)->toBe($user->id);
});

test('selling a rented property asks what happens to the lease', function () {
    ['user' => $user, 'account' => $account, 'property' => $property, 'deal' => $deal] = saleScenario();
    Lease::factory()->for($account, 'account')->for($property, 'property')->create();

    $this->actingAs($user)
        ->post(route('deals.sale.store', $deal), salePayload())
        ->assertSessionHasErrors(['rental_outcome' => 'Este imóvel está alugado. Escolha o que acontece com o contrato de aluguel.']);

    expect(Sale::where('deal_id', $deal->id)->exists())->toBeFalse();
});

test('selling a rented property can terminate its lease', function (bool $agency) {
    ['user' => $user, 'account' => $account, 'property' => $property, 'deal' => $deal] = saleScenario($agency);
    $lease = Lease::factory()->for($account, 'account')->for($property, 'property')->create();
    $property->update(['status' => PropertyStatus::Rented]);
    $upcoming = Payment::factory()->for($lease, 'lease')->create(['reference_month' => '2026-11-01', 'due_date' => '2026-11-10']);

    $this->actingAs($user)
        ->post(route('deals.sale.store', $deal), salePayload(['rental_outcome' => 'terminate']))
        ->assertSessionHasNoErrors();

    expect($lease->refresh()->status)->toBe(LeaseStatus::Terminated)
        ->and($upcoming->refresh()->status)->toBe(PaymentStatus::Canceled)
        ->and($property->refresh()->status)->toBe(PropertyStatus::Sold)
        ->and(Sale::where('deal_id', $deal->id)->sole()->rental_outcome)->toBe(RentalOutcome::Terminate);
})->with(['single owner' => false, 'agency' => true]);

test('selling a rented property can keep its lease going', function () {
    ['user' => $user, 'account' => $account, 'property' => $property, 'deal' => $deal] = saleScenario(agency: true);
    $lease = Lease::factory()->for($account, 'account')->for($property, 'property')->create();
    $property->update(['status' => PropertyStatus::Rented]);

    $this->actingAs($user)
        ->post(route('deals.sale.store', $deal), salePayload(['rental_outcome' => 'keep']))
        ->assertSessionHasNoErrors();

    $property->refresh();

    expect($lease->refresh()->status)->toBe(LeaseStatus::Active)
        ->and($property->status)->toBe(PropertyStatus::Rented)
        ->and($property->for_sale)->toBeFalse()
        ->and($property->for_rent)->toBeTrue();

    $sale = Sale::where('deal_id', $deal->id)->sole();

    expect($sale->rental_outcome)->toBe(RentalOutcome::Keep);

    $this->actingAs($user)->delete(route('sales.destroy', $sale));

    expect($property->refresh()->for_sale)->toBeTrue()
        ->and($property->status)->toBe(PropertyStatus::Rented);
});

test('only open sale deals with a property can be closed as a sale', function () {
    ['user' => $user, 'deal' => $deal] = saleScenario();
    $deal->update(['property_id' => null]);

    $this->actingAs($user)
        ->post(route('deals.sale.store', $deal), salePayload())
        ->assertSessionHasErrors(['deal' => 'Escolha o imóvel da negociação antes de registrar a venda.']);

    $deal->update(['type' => 'rent']);

    $this->actingAs($user)
        ->post(route('deals.sale.store', $deal), salePayload())
        ->assertSessionHasErrors(['deal' => 'Só negociações de venda em andamento podem ser fechadas como venda.']);
});

test('the sales page sums up the sales of the month', function () {
    ['user' => $user, 'account' => $account, 'property' => $property] = saleScenario();
    Sale::factory()->for($account, 'account')->create(['property_id' => $property->id, 'price' => 200000, 'commission_amount' => 12000, 'sold_on' => '2026-10-03']);
    Sale::factory()->for($account, 'account')->create(['property_id' => $property->id, 'price' => 100000, 'sold_on' => '2026-10-10']);
    Sale::factory()->for($account, 'account')->create(['property_id' => $property->id, 'price' => 999999, 'sold_on' => '2026-09-30']);
    Sale::factory()->create();

    $this->actingAs($user)
        ->get(route('sales.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/Index')
            ->where('month', '2026-10')
            ->has('sales', 2)
            ->where('summary.count', 2)
            ->where('summary.total', 300000)
            ->where('summary.commission', 12000)
        );

    $this->actingAs($user)
        ->get(route('sales.index', ['month' => '2026-09']))
        ->assertInertia(fn (Assert $page) => $page->has('sales', 1));
});

test('the dashboard shows the sales once the account uses the funnel', function () {
    $newcomer = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($newcomer)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('commercial', null));

    ['user' => $user, 'deal' => $deal] = saleScenario();
    $deal->visits()->create(['account_id' => $deal->account_id, 'scheduled_at' => '2026-10-16 10:00:00']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('commercial.openDeals', 1)
            ->has('commercial.upcomingVisits', 1)
            ->where('commercial.upcomingVisits.0.deal.lead.name', 'Carlos Comprador')
        );
});

test('agencies set the commission they usually charge', function () {
    $user = User::factory()->for(Account::factory()->agency(), 'account')->create();

    $this->actingAs($user)
        ->patch(route('agency.update'), [
            'name' => 'Imobiliária Silva',
            'document' => '12345678000195',
            'phone' => '8738741234',
            'sale_commission_percent' => '5.5',
        ])
        ->assertSessionHasNoErrors();

    expect($user->account->refresh()->sale_commission_percent)->toBe('5.50');
});

test('a sale opens with its details, whatever month is shown', function () {
    ['user' => $user, 'account' => $account, 'property' => $property, 'owner' => $owner] = saleScenario();
    $sale = Sale::factory()->for($account, 'account')->create([
        'property_id' => $property->id,
        'seller_owner_id' => $owner->id,
        'buyer_name' => 'Ana Compradora',
        'sold_on' => '2026-08-20',
    ]);

    $this->actingAs($user)
        ->get(route('sales.index', ['show' => $sale->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.id', $sale->id)
            ->where('selected.buyer_name', 'Ana Compradora')
            ->where('selected.seller.name', $owner->name)
            ->where('selected.property.street', $property->street)
        );

    $this->actingAs($user)
        ->get(route('sales.index', ['show' => Sale::factory()->create()->id]))
        ->assertInertia(fn (Assert $page) => $page->where('selected', null));
});

test('closed deals point to their sale', function () {
    ['user' => $user, 'deal' => $deal] = saleScenario();

    $this->actingAs($user)->post(route('deals.sale.store', $deal), salePayload());

    $this->actingAs($user)
        ->get(route('deals.index', ['show' => $deal->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.sale.id', Sale::where('deal_id', $deal->id)->value('id'))
        );
});

test('deleting a sale offers the property again and brings the deals back to the funnel', function () {
    ['user' => $user, 'account' => $account, 'property' => $property, 'deal' => $deal, 'stages' => $stages] = saleScenario();
    $competingDeal = Deal::factory()->for($account, 'account')->create(['property_id' => $property->id, 'deal_stage_id' => $stages[0]->id]);

    $this->actingAs($user)->post(route('deals.sale.store', $deal), salePayload());

    $sale = Sale::where('deal_id', $deal->id)->sole();

    $this->actingAs($user)
        ->delete(route('sales.destroy', $sale))
        ->assertRedirect(route('sales.index', ['month' => '2026-10']));

    $this->assertModelMissing($sale);

    $property->refresh();

    expect($property->status)->toBe(PropertyStatus::Available)
        ->and($property->for_sale)->toBeTrue()
        ->and($property->for_rent)->toBeTrue();

    $deal->refresh();

    expect($deal->closed_at)->toBeNull()
        ->and($deal->deal_stage_id)->toBe($stages[3]->id);

    $competingDeal->refresh();

    expect($competingDeal->closed_at)->toBeNull()
        ->and($competingDeal->lost_reason)->toBeNull()
        ->and($competingDeal->deal_stage_id)->toBe($stages[0]->id);
});

test('only people who handle sales may delete them, and only their own', function () {
    ['account' => $account, 'property' => $property] = saleScenario(agency: true);
    $sale = Sale::factory()->for($account, 'account')->create(['property_id' => $property->id]);
    $finance = User::factory()->for($account, 'account')->withRole(Role::Finance)->create();
    $finance->branches()->attach($property->branch_id);
    $stranger = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($finance)->delete(route('sales.destroy', $sale))->assertForbidden();
    $this->actingAs($stranger)->delete(route('sales.destroy', $sale))->assertNotFound();

    $this->assertModelExists($sale);
});

test('a sale can be registered straight for a property, without the funnel', function () {
    ['user' => $user, 'account' => $account, 'owner' => $owner, 'property' => $property, 'deal' => $deal] = saleScenario();

    $this->actingAs($user)
        ->post(route('sales.store'), salePayload(['property_id' => $property->id]))
        ->assertSessionHasNoErrors();

    $sale = Sale::where('property_id', $property->id)->sole();

    expect($sale->deal_id)->toBeNull()
        ->and($sale->lead_id)->toBeNull()
        ->and($sale->account_id)->toBe($account->id)
        ->and($sale->seller_owner_id)->toBe($owner->id)
        ->and($sale->price)->toBe('290000.00');

    expect($property->refresh()->status)->toBe(PropertyStatus::Sold);

    $deal->refresh();

    expect($deal->stage->outcome)->toBe(DealOutcome::Lost)
        ->and($deal->lost_reason)->toBe('Imóvel vendido para outro comprador');

    $this->actingAs($user)->delete(route('sales.destroy', $sale));

    expect($deal->refresh()->closed_at)->toBeNull()
        ->and($property->refresh()->status)->toBe(PropertyStatus::Available);
});

test('a direct sale opens its details on the sales page', function () {
    ['user' => $user, 'property' => $property] = saleScenario();

    $response = $this->actingAs($user)->post(route('sales.store'), salePayload(['property_id' => $property->id]));

    $response->assertRedirect(route('sales.index', ['month' => '2026-10', 'show' => Sale::sole()->id]));
});

test('accounts that never used the funnel can register direct sales', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();
    $property = Property::factory()->for($account, 'account')->create(['for_sale' => true, 'sale_price' => 150000]);

    $this->actingAs($user)
        ->post(route('sales.store'), salePayload(['property_id' => $property->id]))
        ->assertSessionHasNoErrors();

    expect($property->refresh()->status)->toBe(PropertyStatus::Sold);
});

test('a direct sale of a rented property asks what happens to the lease', function () {
    ['user' => $user, 'account' => $account, 'property' => $property] = saleScenario();
    $lease = Lease::factory()->for($account, 'account')->for($property, 'property')->create();
    $property->update(['status' => PropertyStatus::Rented]);

    $this->actingAs($user)
        ->post(route('sales.store'), salePayload(['property_id' => $property->id]))
        ->assertSessionHasErrors('rental_outcome');

    $this->actingAs($user)
        ->post(route('sales.store'), salePayload(['property_id' => $property->id, 'rental_outcome' => 'keep']))
        ->assertSessionHasNoErrors();

    expect($property->refresh()->status)->toBe(PropertyStatus::Rented)
        ->and($property->for_sale)->toBeFalse()
        ->and($lease->refresh()->status)->toBe(LeaseStatus::Active);
});

test('direct sales only take visible properties for sale that were not sold yet', function () {
    ['user' => $user, 'account' => $account, 'property' => $property] = saleScenario();
    $forRentOnly = Property::factory()->for($account, 'account')->create(['for_sale' => false]);
    $otherAccounts = Property::factory()->create(['for_sale' => true]);

    $this->actingAs($user)
        ->post(route('sales.store'), salePayload())
        ->assertSessionHasErrors('property_id');

    $this->actingAs($user)
        ->post(route('sales.store'), salePayload(['property_id' => $forRentOnly->id]))
        ->assertSessionHasErrors(['property_id' => 'Este imóvel não está à venda.']);

    $this->actingAs($user)
        ->post(route('sales.store'), salePayload(['property_id' => $otherAccounts->id]))
        ->assertSessionHasErrors(['property_id' => 'Escolha o imóvel vendido.']);

    $property->update(['status' => PropertyStatus::Sold]);

    $this->actingAs($user)
        ->post(route('sales.store'), salePayload(['property_id' => $property->id]))
        ->assertSessionHasErrors(['property_id' => 'Este imóvel já foi vendido.']);

    expect(Sale::count())->toBe(0);
});

test('only people who handle sales may register direct sales', function () {
    ['account' => $account, 'property' => $property] = saleScenario(agency: true);
    $finance = User::factory()->for($account, 'account')->withRole(Role::Finance)->create();
    $finance->branches()->attach($property->branch_id);

    $this->actingAs($finance)
        ->post(route('sales.store'), salePayload(['property_id' => $property->id]))
        ->assertForbidden();

    expect(Sale::count())->toBe(0);
});

test('the sales page offers the properties for sale and opens the one requested', function () {
    ['user' => $user, 'account' => $account, 'property' => $property] = saleScenario();
    Property::factory()->for($account, 'account')->create(['for_sale' => false]);
    Property::factory()->for($account, 'account')->create(['for_sale' => true, 'status' => PropertyStatus::Sold]);

    $this->actingAs($user)
        ->get(route('sales.index', ['sell' => $property->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties', 1)
            ->where('properties.0.id', $property->id)
            ->where('sellPropertyId', $property->id)
        );

    $this->actingAs($user)
        ->get(route('sales.index', ['sell' => Property::factory()->create(['for_sale' => true])->id]))
        ->assertInertia(fn (Assert $page) => $page->where('sellPropertyId', null));
});
