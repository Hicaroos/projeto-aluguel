<?php

use App\Enums\Role;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Owner;
use App\Models\Property;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An agency with two branches and an owner with a property in Ouricuri.
 *
 * @return array{account: Account, user: User, ouricuri: Branch, araripina: Branch, owner: Owner, property: Property}
 */
function ownerScenario(): array
{
    $account = Account::factory()->agency()->create();
    $ouricuri = Branch::factory()->for($account, 'account')->create(['name' => 'Ouricuri']);
    $owner = Owner::factory()->for($account, 'account')->create(['name' => 'Dona Maria']);

    return [
        'account' => $account,
        'user' => User::factory()->for($account, 'account')->create(),
        'ouricuri' => $ouricuri,
        'araripina' => Branch::factory()->for($account, 'account')->create(['name' => 'Araripina']),
        'owner' => $owner,
        'property' => Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['branch_id' => $ouricuri->id]),
    ];
}

/**
 * @return array<string, mixed>
 */
function ownerPayload(array $overrides = []): array
{
    return [
        'name' => 'João Proprietário',
        'cpf_cnpj' => '123.456.789-00',
        'phone' => '(87) 99999-0000',
        'email' => 'joao@example.com',
        'pix_key' => 'joao@example.com',
        ...$overrides,
    ];
}

test('agencies list their owners with their properties', function () {
    ['user' => $user, 'owner' => $owner, 'property' => $property] = ownerScenario();
    Owner::factory()->create();

    $this->actingAs($user)
        ->get(route('owners.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('owners/Index')
            ->has('owners.data', 1)
            ->where('owners.data.0.id', $owner->id)
            ->where('owners.data.0.properties_count', 1)
            ->where('owners.data.0.properties.0.id', $property->id)
            ->where('owners.data.0.properties.0.branch.name', 'Ouricuri')
        );
});

test('single owners keep their details in the settings instead', function () {
    $user = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($user)->get(route('owners.index'))->assertNotFound();
});

test('owners can be registered with their contact and pix key', function () {
    ['user' => $user, 'account' => $account] = ownerScenario();

    $this->actingAs($user)
        ->post(route('owners.store'), ownerPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('owners.index'));

    $this->assertDatabaseHas('owners', [
        'account_id' => $account->id,
        'name' => 'João Proprietário',
        'cpf_cnpj' => '12345678900',
        'phone' => '87999990000',
        'pix_key' => 'joao@example.com',
    ]);
});

test('owners need a name, a valid document and a phone, never registered twice', function () {
    ['user' => $user, 'owner' => $owner] = ownerScenario();

    $this->actingAs($user)
        ->post(route('owners.store'), ownerPayload(['name' => '', 'cpf_cnpj' => '123', 'phone' => '']))
        ->assertSessionHasErrors(['name', 'cpf_cnpj', 'phone']);

    $this->actingAs($user)
        ->post(route('owners.store'), ownerPayload(['cpf_cnpj' => $owner->cpf_cnpj]))
        ->assertSessionHasErrors(['cpf_cnpj' => 'Já existe um proprietário com este CPF/CNPJ.']);
});

test('owners can be updated', function () {
    ['user' => $user, 'owner' => $owner] = ownerScenario();

    $this->actingAs($user)
        ->put(route('owners.update', $owner), ownerPayload(['cpf_cnpj' => $owner->cpf_cnpj, 'name' => 'Maria Souza']))
        ->assertSessionHasNoErrors();

    expect($owner->refresh()->name)->toBe('Maria Souza');
});

test('owners with properties cannot be removed', function () {
    ['user' => $user, 'account' => $account, 'owner' => $owner] = ownerScenario();
    $ownerWithoutProperties = Owner::factory()->for($account, 'account')->create();

    $this->actingAs($user)->delete(route('owners.destroy', $owner));
    $this->assertNotSoftDeleted($owner);

    $this->actingAs($user)->delete(route('owners.destroy', $ownerWithoutProperties))->assertRedirect(route('owners.index'));
    $this->assertSoftDeleted($ownerWithoutProperties);
});

test('owners of other accounts are out of reach', function () {
    ['user' => $user] = ownerScenario();
    $stranger = Owner::factory()->create();

    $this->actingAs($user)->put(route('owners.update', $stranger), ownerPayload())->assertNotFound();
    $this->actingAs($user)->delete(route('owners.destroy', $stranger))->assertNotFound();
});

test('properties link their owner to their branch, so each branch lists its owners', function () {
    ['user' => $user, 'owner' => $owner, 'ouricuri' => $ouricuri, 'araripina' => $araripina] = ownerScenario();

    expect($owner->branches()->pluck('branches.id')->all())->toBe([$ouricuri->id]);

    $this->actingAs($user)
        ->withSession([User::SELECTED_BRANCH_SESSION_KEY => $araripina->id])
        ->get(route('owners.index'))
        ->assertInertia(fn (Assert $page) => $page->has('owners.data', 0));
});

test('an owner of another branch can be brought to the selected branch instead of registered twice', function () {
    ['user' => $user, 'owner' => $owner, 'ouricuri' => $ouricuri, 'araripina' => $araripina] = ownerScenario();

    $this->actingAs($user)
        ->withSession([User::SELECTED_BRANCH_SESSION_KEY => $araripina->id])
        ->post(route('owners.store'), ownerPayload(['cpf_cnpj' => $owner->cpf_cnpj]))
        ->assertSessionHasErrors([
            'cpf_cnpj' => 'Este CPF/CNPJ já está cadastrado em outra unidade.',
            'registered_elsewhere',
        ]);

    $this->post(route('owners.link'), ['cpf_cnpj' => $owner->cpf_cnpj])
        ->assertRedirect(route('owners.index', ['show' => $owner->id]));

    expect($owner->branches()->pluck('branches.id')->sort()->values()->all())
        ->toBe(collect([$ouricuri->id, $araripina->id])->sort()->values()->all());
});

test('properties can only belong to owners the user sees', function () {
    ['account' => $account, 'owner' => $owner, 'araripina' => $araripina] = ownerScenario();
    $agent = User::factory()->for($account, 'account')->withRole(Role::Agent)->create();
    $agent->branches()->attach($araripina);

    $this->actingAs($agent)
        ->post(route('properties.store'), [
            'type' => 'house',
            'branch_id' => $araripina->id,
            'owner_id' => $owner->id,
            'zip_code' => '56280-000',
            'street' => 'Rua Nova',
            'number' => '1',
            'neighborhood' => 'Centro',
            'city' => 'Araripina',
            'state' => 'PE',
            'rent_amount' => '900.00',
            'status' => 'available',
        ])
        ->assertSessionHasErrors('owner_id');
});

test('finance members look at owners without changing them', function () {
    ['account' => $account, 'owner' => $owner, 'ouricuri' => $ouricuri] = ownerScenario();
    $finance = User::factory()->for($account, 'account')->withRole(Role::Finance)->create();
    $finance->branches()->attach($ouricuri);

    $this->actingAs($finance)->get(route('owners.index'))->assertOk();
    $this->actingAs($finance)->post(route('owners.store'), ownerPayload())->assertForbidden();
    $this->actingAs($finance)->delete(route('owners.destroy', $owner))->assertForbidden();
});
