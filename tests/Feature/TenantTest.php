<?php

use App\Models\Account;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function validTenantPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Maria Silva',
        'cpf_cnpj' => '12345678900',
        'email' => 'maria@example.com',
        'phone' => '11999999999',
    ], $overrides);
}

test('guests are redirected to the login page', function () {
    $this->get(route('tenants.index'))->assertRedirect(route('login'));
});

test('index only lists tenants belonging to the authenticated account', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();
    Tenant::factory()->for($account, 'account')->count(2)->create();

    Tenant::factory()->create();

    $this->actingAs($user)
        ->get(route('tenants.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenants/Index')
            ->has('tenants.data', 2)
        );
});

test('a tenant can be created for the authenticated account', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();

    $response = $this->actingAs($user)->post(route('tenants.store'), validTenantPayload());

    $response->assertSessionHasNoErrors()->assertRedirect(route('tenants.index'));

    $this->assertDatabaseHas('tenants', [
        'account_id' => $account->id,
        'name' => 'Maria Silva',
        'cpf_cnpj' => '12345678900',
    ]);
});

test('only the name is required to create a tenant', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->post(route('tenants.store'), ['name' => 'João Souza'])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('tenants.store'), validTenantPayload(['name' => '', 'email' => 'invalid']))
        ->assertSessionHasErrors(['name', 'email']);
});

test('cpf_cnpj must be unique within the same account', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();
    Tenant::factory()->for($account, 'account')->create(['cpf_cnpj' => '12345678900']);

    $this->actingAs($user)
        ->post(route('tenants.store'), validTenantPayload())
        ->assertSessionHasErrors('cpf_cnpj');
});

test('the same cpf_cnpj can be used by tenants of different accounts', function () {
    Tenant::factory()->create(['cpf_cnpj' => '12345678900']);

    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->post(route('tenants.store'), validTenantPayload())
        ->assertSessionHasNoErrors();
});

test('a tenant can be updated keeping its own cpf_cnpj and then deleted', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();
    $tenant = Tenant::factory()->for($account, 'account')->create(['cpf_cnpj' => '12345678900']);

    $updateResponse = $this->actingAs($user)->put(
        route('tenants.update', $tenant),
        validTenantPayload(['name' => 'Novo Nome']),
    );

    $updateResponse->assertSessionHasNoErrors()->assertRedirect(route('tenants.index'));
    expect($tenant->fresh()->name)->toBe('Novo Nome');

    $deleteResponse = $this->actingAs($user)->delete(route('tenants.destroy', $tenant));

    $deleteResponse->assertRedirect(route('tenants.index'));
    expect($tenant->fresh()->trashed())->toBeTrue();
});

test('a user cannot update or delete a tenant from another account', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();
    $otherTenant = Tenant::factory()->create();

    $this->actingAs($user)->put(route('tenants.update', $otherTenant), validTenantPayload())->assertNotFound();
    $this->actingAs($user)->delete(route('tenants.destroy', $otherTenant))->assertNotFound();

    expect($otherTenant->fresh()->trashed())->toBeFalse();
});

test('index filters tenants by search term', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();

    Tenant::factory()->for($account, 'account')->create(['name' => 'Ana Pereira']);
    Tenant::factory()->for($account, 'account')->create(['name' => 'Carlos Lima']);

    $this->actingAs($user)
        ->get(route('tenants.index', ['search' => 'Carlos']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenants/Index')
            ->has('tenants.data', 1)
            ->where('tenants.data.0.name', 'Carlos Lima')
        );
});

test('index paginates tenants', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();

    Tenant::factory()->for($account, 'account')->count(11)->create();

    $this->actingAs($user)
        ->get(route('tenants.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenants/Index')
            ->has('tenants.data', 10)
            ->where('tenants.last_page', 2)
        );
});
