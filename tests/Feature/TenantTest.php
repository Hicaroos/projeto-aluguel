<?php

use App\Enums\MaritalStatus;
use App\Models\Account;
use App\Models\Lease;
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

test('name, cpf/cnpj and mobile phone are required to create a tenant', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->post(route('tenants.store'), ['name' => 'João Souza', 'cpf_cnpj' => '98765432100', 'phone' => '11988887777'])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('tenants.store'), validTenantPayload(['name' => '', 'cpf_cnpj' => '', 'phone' => '', 'email' => 'invalid']))
        ->assertSessionHasErrors([
            'name',
            'cpf_cnpj',
            'phone' => 'O campo celular é obrigatório.',
            'email',
        ]);
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

test('index includes the leases of each tenant and can preselect a tenant', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();
    $tenant = Tenant::factory()->for($account, 'account')->create();
    $endedLease = Lease::factory()->ended()->for($account, 'account')->for($tenant)->create(['start_date' => '2024-01-01']);
    $activeLease = Lease::factory()->for($account, 'account')->for($tenant)->create(['start_date' => '2025-01-01']);

    $this->actingAs($user)
        ->get(route('tenants.index', ['show' => $tenant->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tenants.data.0.leases', 2)
            ->where('tenants.data.0.leases.0.id', $activeLease->id)
            ->where('tenants.data.0.leases.1.id', $endedLease->id)
            ->has('tenants.data.0.leases.0.property.street')
            ->where('selected.id', $tenant->id)
        );
});

test('pagination links do not carry the preselected tenant', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();
    $tenant = Tenant::factory()->for($account, 'account')->create();
    Tenant::factory()->for($account, 'account')->count(11)->create();

    $this->actingAs($user)
        ->get(route('tenants.index', ['show' => $tenant->id, 'search' => '']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.id', $tenant->id)
            ->where('tenants.next_page_url', fn (string $url) => ! str_contains($url, 'show='))
        );
});

test('index sorts tenants by name in both directions', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();
    Tenant::factory()->for($account, 'account')->create(['name' => 'Bruno']);
    Tenant::factory()->for($account, 'account')->create(['name' => 'Ana']);

    $this->actingAs($user)
        ->get(route('tenants.index', ['sort' => 'name', 'direction' => 'desc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenants.data.0.name', 'Bruno')
            ->where('filters.direction', 'desc')
        );
});

test('a tenant stores the qualification and address used in lease contracts', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->post(route('tenants.store'), validTenantPayload([
            'rg' => '12.345.678-9',
            'nationality' => 'brasileira',
            'marital_status' => 'married',
            'profession' => 'Engenheira',
            'zip_code' => '01310-100',
            'street' => 'Avenida Paulista',
            'number' => '1000',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]))
        ->assertSessionHasNoErrors();

    $tenant = Tenant::first();

    expect($tenant->marital_status)->toBe(MaritalStatus::Married)
        ->and($tenant->profession)->toBe('Engenheira')
        ->and($tenant->city)->toBe('São Paulo');
});

test('a tenant rejects an invalid marital status and state', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->post(route('tenants.store'), validTenantPayload(['marital_status' => 'complicated', 'state' => 'São Paulo']))
        ->assertSessionHasErrors(['marital_status', 'state']);

    expect(Tenant::count())->toBe(0);
});
