<?php

use App\Actions\Properties\SyncPropertyOwners;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
});

/**
 * An agency with a house Maria (60%) and João (40%) own together and an apartment of Antônio, both rented,
 * each with a rent due this month and a paid expense.
 *
 * @return array{account: Account, user: User, maria: Owner, joao: Owner, antonio: Owner, house: Property, apartment: Property}
 */
function ownerSelectionScenario(): array
{
    $account = Account::factory()->agency()->create();
    $maria = Owner::factory()->for($account, 'account')->create(['name' => 'Maria']);
    $joao = Owner::factory()->for($account, 'account')->create(['name' => 'João']);
    $antonio = Owner::factory()->for($account, 'account')->create(['name' => 'Antônio']);

    $house = Property::factory()->for($account, 'account')->for($maria, 'owner')->create(['street' => 'Rua das Flores']);
    app(SyncPropertyOwners::class)->handle($house, [$joao->id => 40]);
    $apartment = Property::factory()->for($account, 'account')->for($antonio, 'owner')->create(['street' => 'Avenida Brasil']);

    foreach ([[$house, 2000, 500], [$apartment, 1000, 300]] as [$property, $rent, $expense]) {
        $lease = Lease::factory()->for($account, 'account')->for($property)->for(Tenant::factory()->for($account, 'account'))->create(['amount' => $rent]);
        Payment::factory()->for($lease)->create(['account_id' => $account->id, 'amount' => $rent, 'due_date' => '2026-10-20']);
        Expense::factory()->paid()->for($account, 'account')->for($property)->create(['amount' => $expense, 'due_date' => '2026-10-05']);
    }

    return [
        'account' => $account,
        'user' => User::factory()->for($account, 'account')->create(),
        'maria' => $maria,
        'joao' => $joao,
        'antonio' => $antonio,
        'house' => $house,
        'apartment' => $apartment,
    ];
}

test('the owner selector shows once there is more than one owner', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();
    Owner::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('ownerSelector', null));

    Owner::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('ownerSelector', ['selected' => null]));
});

test('the selector lists the owners of the account', function () {
    ['user' => $user] = ownerSelectionScenario();
    Owner::factory()->create();

    $this->actingAs($user)
        ->getJson(route('owner-selection.index'))
        ->assertOk()
        ->assertJsonCount(3)
        ->assertJsonPath('0.name', 'Antônio');
});

test('picking an owner shows only their properties, co-owned ones included, and what belongs to them', function () {
    ['user' => $user, 'joao' => $joao, 'house' => $house] = ownerSelectionScenario();

    $this->actingAs($user)
        ->from(route('properties.index'))
        ->put(route('owner-selection.update'), ['owner_id' => $joao->id])
        ->assertRedirect(route('properties.index'))
        ->assertSessionHas(User::SELECTED_OWNER_SESSION_KEY, $joao->id);

    $this->actingAs($user)
        ->get(route('properties.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ownerSelector.selected', ['id' => $joao->id, 'name' => 'João'])
            ->has('properties.data', 1)
            ->where('properties.data.0.id', $house->id)
        );

    $this->actingAs($user)->get(route('leases.index'))->assertInertia(fn (Assert $page) => $page->has('leases.data', 1));
    $this->actingAs($user)->get(route('payments.index'))->assertInertia(fn (Assert $page) => $page->has('payments.data', 1));
    $this->actingAs($user)->get(route('expenses.index'))->assertInertia(fn (Assert $page) => $page->has('expenses.data', 1));
    $this->actingAs($user)->get(route('tenants.index'))->assertInertia(fn (Assert $page) => $page->has('tenants.data', 1));
    $this->actingAs($user)->get(route('owners.index'))->assertInertia(fn (Assert $page) => $page
        ->has('owners.data', 1)
        ->where('owners.data.0.id', $joao->id)
    );
});

test('leases still offer every tenant, so a new tenant can rent the owner property', function () {
    ['user' => $user, 'account' => $account, 'joao' => $joao] = ownerSelectionScenario();
    Tenant::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->withSession([User::SELECTED_OWNER_SESSION_KEY => $joao->id])
        ->get(route('leases.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties', 1)
            ->has('tenants', 3)
        );
});

test('leads, deals and sales show everything whatever owner is picked', function () {
    ['user' => $user, 'account' => $account, 'joao' => $joao, 'apartment' => $apartment] = ownerSelectionScenario();
    Sale::factory()->for($account, 'account')->create(['property_id' => $apartment->id]);

    $this->actingAs($user)
        ->withSession([User::SELECTED_OWNER_SESSION_KEY => $joao->id])
        ->get(route('sales.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('sales', 1)
            ->where('sales.0.property.id', $apartment->id)
        );
});

test('the payouts open the statement of the picked owner', function () {
    ['user' => $user, 'joao' => $joao, 'maria' => $maria] = ownerSelectionScenario();

    $this->actingAs($user)
        ->withSession([User::SELECTED_OWNER_SESSION_KEY => $joao->id])
        ->get(route('payouts.index'))
        ->assertRedirect(route('payouts.show', $joao));

    $this->actingAs($user)
        ->withSession([User::SELECTED_OWNER_SESSION_KEY => $joao->id])
        ->get(route('payouts.show', ['owner' => $maria->id, 'month' => '2026-09']))
        ->assertRedirect(route('payouts.show', ['owner' => $joao->id, 'month' => '2026-09']));
});

test('clearing the selection shows every owner again', function () {
    ['user' => $user, 'joao' => $joao] = ownerSelectionScenario();

    $this->actingAs($user)
        ->withSession([User::SELECTED_OWNER_SESSION_KEY => $joao->id])
        ->put(route('owner-selection.update'), ['owner_id' => null])
        ->assertSessionMissing(User::SELECTED_OWNER_SESSION_KEY);

    $this->actingAs($user)
        ->get(route('properties.index'))
        ->assertInertia(fn (Assert $page) => $page->has('properties.data', 2)->where('ownerSelector.selected', null));
});

test('only owners of the account can be picked, and a removed owner stops filtering', function () {
    ['user' => $user, 'antonio' => $antonio] = ownerSelectionScenario();
    $stranger = Owner::factory()->create();

    $this->actingAs($user)
        ->put(route('owner-selection.update'), ['owner_id' => $stranger->id])
        ->assertSessionHasErrors(['owner_id' => 'Proprietário não encontrado.']);

    $antonio->properties()->detach();
    $antonio->delete();

    $this->actingAs($user)
        ->withSession([User::SELECTED_OWNER_SESSION_KEY => $antonio->id])
        ->get(route('properties.index'))
        ->assertInertia(fn (Assert $page) => $page->has('properties.data', 2));
});

test('documents stay unique across owners while one is picked', function () {
    ['user' => $user, 'account' => $account, 'joao' => $joao] = ownerSelectionScenario();
    Tenant::factory()->for($account, 'account')->create(['cpf_cnpj' => '11122233344']);

    $this->actingAs($user)
        ->withSession([User::SELECTED_OWNER_SESSION_KEY => $joao->id])
        ->post(route('tenants.store'), ['name' => 'Outra Pessoa', 'cpf_cnpj' => '111.222.333-44'])
        ->assertSessionHasErrors(['cpf_cnpj' => 'Já existe um inquilino com este CPF/CNPJ.']);
});
