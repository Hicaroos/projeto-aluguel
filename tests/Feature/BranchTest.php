<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An agency with two branches, each with a rented property.
 *
 * @return array{account: Account, user: User, owner: Owner, ouricuri: Branch, araripina: Branch, ouricuriLease: Lease, araripinaLease: Lease}
 */
function branchScenario(): array
{
    $account = Account::factory()->agency()->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $ouricuri = Branch::factory()->for($account, 'account')->create(['name' => 'Ouricuri']);
    $araripina = Branch::factory()->for($account, 'account')->create(['name' => 'Araripina']);

    $leaseIn = fn (Branch $branch): Lease => Lease::factory()->for($account, 'account')->create([
        'property_id' => Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['branch_id' => $branch->id])->id,
        'tenant_id' => Tenant::factory()->for($account, 'account')->create()->id,
    ])->load(['property', 'tenant']);

    return [
        'account' => $account,
        'user' => User::factory()->for($account, 'account')->create(),
        'owner' => $owner,
        'ouricuri' => $ouricuri,
        'araripina' => $araripina,
        'ouricuriLease' => $leaseIn($ouricuri),
        'araripinaLease' => $leaseIn($araripina),
    ];
}

/**
 * @return array<string, mixed>
 */
function branchPropertyPayload(array $overrides = []): array
{
    return [
        'type' => 'house',
        'zip_code' => '56200-000',
        'street' => 'Rua Teste',
        'number' => '100',
        'neighborhood' => 'Centro',
        'city' => 'Ouricuri',
        'state' => 'PE',
        'rent_amount' => '1500.00',
        'status' => 'available',
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function branchTenantPayload(array $overrides = []): array
{
    return [
        'name' => 'Maria Silva',
        'cpf_cnpj' => '12345678900',
        'phone' => '87999999999',
        ...$overrides,
    ];
}

/**
 * @return array<string, mixed>
 */
function branchLeasePayload(Property $property, Tenant $tenant): array
{
    return [
        'property_id' => $property->id,
        'tenant_id' => $tenant->id,
        'start_date' => '2026-01-01',
        'end_date' => '2028-06-30',
        'amount' => '1800.00',
        'due_day' => 10,
        'guarantee_type' => 'none',
    ];
}

test('agency admins see their branches with what each one manages', function () {
    ['user' => $user, 'ouricuri' => $ouricuri] = branchScenario();
    Branch::factory()->create(['name' => 'Outra imobiliária']);

    $this->actingAs($user)
        ->get(route('branches.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('branches/Index')
            ->has('branches', 2)
            ->where('branches.1.name', $ouricuri->name)
            ->where('branches.1.properties_count', 1)
            ->where('branches.1.active_leases_count', 1)
        );
});

test('single owners have no branches to manage', function () {
    $user = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($user)->get(route('branches.index'))->assertForbidden();
    $this->actingAs($user)->post(route('branches.store'), ['name' => 'Centro'])->assertForbidden();
});

test('agency admins can create and update branches', function () {
    ['user' => $user, 'account' => $account, 'ouricuri' => $ouricuri] = branchScenario();

    $this->actingAs($user)
        ->post(route('branches.store'), [
            'name' => 'Exu',
            'phone' => '(87) 3874-1234',
            'document' => '12.345.678/0001-90',
            'creci' => '1234-J',
            'city' => 'Exu',
            'state' => 'PE',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('branches.index'));

    $this->assertDatabaseHas('branches', [
        'account_id' => $account->id,
        'name' => 'Exu',
        'phone' => '8738741234',
        'document' => '12345678000190',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->put(route('branches.update', $ouricuri), ['name' => 'Ouricuri Centro'])
        ->assertSessionHasNoErrors();

    expect($ouricuri->refresh()->name)->toBe('Ouricuri Centro');
});

test('branch names are unique within the agency', function () {
    ['user' => $user] = branchScenario();

    $this->actingAs($user)
        ->post(route('branches.store'), ['name' => 'Ouricuri'])
        ->assertSessionHasErrors(['name' => 'Já existe uma unidade com este nome.']);
});

test('branches of other accounts cannot be changed', function () {
    ['user' => $user] = branchScenario();
    $otherBranch = Branch::factory()->create();

    $this->actingAs($user)->put(route('branches.update', $otherBranch), ['name' => 'Invadida'])->assertNotFound();
    $this->actingAs($user)->patch(route('branches.status', $otherBranch))->assertNotFound();
    $this->actingAs($user)->delete(route('branches.destroy', $otherBranch))->assertNotFound();
});

test('branches can be deactivated and reactivated, but never the last active one', function () {
    ['user' => $user, 'ouricuri' => $ouricuri, 'araripina' => $araripina] = branchScenario();

    $this->actingAs($user)->patch(route('branches.status', $ouricuri))->assertRedirect(route('branches.index'));
    expect($ouricuri->refresh()->is_active)->toBeFalse();

    $this->actingAs($user)->patch(route('branches.status', $araripina));
    expect($araripina->refresh()->is_active)->toBeTrue();

    $this->actingAs($user)->patch(route('branches.status', $ouricuri));
    expect($ouricuri->refresh()->is_active)->toBeTrue();
});

test('only branches that never had properties can be deleted', function () {
    ['user' => $user, 'account' => $account, 'ouricuri' => $ouricuri] = branchScenario();
    $emptyBranch = Branch::factory()->for($account, 'account')->create();

    $this->actingAs($user)->delete(route('branches.destroy', $ouricuri));
    $this->assertModelExists($ouricuri);

    $this->actingAs($user)->delete(route('branches.destroy', $emptyBranch))->assertRedirect(route('branches.index'));
    $this->assertModelMissing($emptyBranch);
});

test('the branch selector is shared with agency users only', function () {
    ['user' => $user, 'ouricuri' => $ouricuri, 'araripina' => $araripina] = branchScenario();
    $araripina->update(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('branchSelector.branches', 1)
            ->where('branchSelector.branches.0.name', $ouricuri->name)
            ->where('branchSelector.selectedId', null)
        );

    $singleOwner = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($singleOwner)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('branchSelector', null));
});

test('selecting a branch filters the whole app', function () {
    ['user' => $user, 'ouricuri' => $ouricuri, 'ouricuriLease' => $ouricuriLease, 'araripinaLease' => $araripinaLease] = branchScenario();
    Payment::factory()->for($ouricuriLease, 'lease')->create();
    Payment::factory()->for($araripinaLease, 'lease')->create();
    Expense::factory()->for($ouricuriLease->property, 'property')->create(['account_id' => $ouricuriLease->account_id]);
    Expense::factory()->for($araripinaLease->property, 'property')->create(['account_id' => $araripinaLease->account_id]);

    $this->actingAs($user)
        ->from(route('properties.index', ['page' => 2]))
        ->put(route('branch-selection.update'), ['branch_id' => $ouricuri->id])
        ->assertRedirect(route('properties.index'));

    $this->get(route('properties.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.id', $ouricuriLease->property_id)
            ->where('branchSelector.selectedId', $ouricuri->id)
        );

    $this->get(route('leases.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('leases.data', 1)
            ->where('leases.data.0.id', $ouricuriLease->id)
        );

    $this->get(route('tenants.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tenants.data', 1)
            ->where('tenants.data.0.id', $ouricuriLease->tenant_id)
        );

    $this->get(route('payments.index'))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1));

    $this->get(route('expenses.index'))
        ->assertInertia(fn (Assert $page) => $page->has('expenses.data', 1));

    $this->put(route('properties.update', $araripinaLease->property), branchPropertyPayload())->assertNotFound();

    $this->put(route('branch-selection.update'), ['branch_id' => null]);

    $this->get(route('properties.index'))
        ->assertInertia(fn (Assert $page) => $page->has('properties.data', 2));
});

test('branches of other accounts cannot be selected', function () {
    ['user' => $user] = branchScenario();
    $otherBranch = Branch::factory()->create();

    $this->actingAs($user)
        ->put(route('branch-selection.update'), ['branch_id' => $otherBranch->id])
        ->assertSessionHasErrors('branch_id');

    expect(session(User::SELECTED_BRANCH_SESSION_KEY))->toBeNull();
});

test('agency properties must be placed in an active branch', function () {
    ['user' => $user, 'owner' => $owner, 'araripina' => $araripina, 'araripinaLease' => $araripinaLease] = branchScenario();
    $araripina->update(['is_active' => false]);

    $this->actingAs($user)
        ->post(route('properties.store'), branchPropertyPayload(['owner_id' => $owner->id, 'branch_id' => $araripina->id]))
        ->assertSessionHasErrors(['branch_id' => 'Selecione uma unidade ativa.']);

    $this->actingAs($user)
        ->put(route('properties.update', $araripinaLease->property), branchPropertyPayload([
            'owner_id' => $owner->id,
            'branch_id' => $araripina->id,
        ]))
        ->assertSessionHasNoErrors();
});

test('moving a property to another branch brings its tenants along', function () {
    ['user' => $user, 'owner' => $owner, 'araripina' => $araripina, 'ouricuriLease' => $ouricuriLease] = branchScenario();

    $this->actingAs($user)
        ->put(route('properties.update', $ouricuriLease->property), branchPropertyPayload([
            'owner_id' => $owner->id,
            'branch_id' => $araripina->id,
        ]))
        ->assertSessionHasNoErrors();

    expect($ouricuriLease->tenant->branches()->pluck('branches.id')->all())->toContain($araripina->id);
});

test('leases link their tenant to the branch of the property', function () {
    ['ouricuri' => $ouricuri, 'ouricuriLease' => $ouricuriLease] = branchScenario();

    expect($ouricuriLease->tenant->branches()->pluck('branches.id')->all())->toBe([$ouricuri->id]);
});

test('tenants registered while a branch is selected belong to it', function () {
    ['user' => $user, 'account' => $account, 'ouricuri' => $ouricuri] = branchScenario();

    $this->actingAs($user)
        ->withSession([User::SELECTED_BRANCH_SESSION_KEY => $ouricuri->id])
        ->post(route('tenants.store'), branchTenantPayload(['cpf_cnpj' => '11122233344']))
        ->assertSessionHasNoErrors();

    $tenant = Tenant::withoutGlobalScopes()->where('account_id', $account->id)->where('cpf_cnpj', '11122233344')->sole();

    expect($tenant->branches()->pluck('branches.id')->all())->toBe([$ouricuri->id]);
});

test('tenants without a branch are seen by every branch', function () {
    ['user' => $user, 'account' => $account, 'araripina' => $araripina] = branchScenario();
    $unlinkedTenant = Tenant::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->withSession([User::SELECTED_BRANCH_SESSION_KEY => $araripina->id])
        ->get(route('tenants.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tenants.data', 2)
            ->where('tenants.data', fn ($tenants) => collect($tenants)->pluck('id')->contains($unlinkedTenant->id))
        );
});

test('a tenant of another branch can be brought to the selected branch instead of registered twice', function () {
    ['user' => $user, 'araripina' => $araripina, 'ouricuriLease' => $ouricuriLease] = branchScenario();
    $tenant = $ouricuriLease->tenant;

    $this->actingAs($user)
        ->withSession([User::SELECTED_BRANCH_SESSION_KEY => $araripina->id])
        ->post(route('tenants.store'), branchTenantPayload(['cpf_cnpj' => $tenant->cpf_cnpj]))
        ->assertSessionHasErrors([
            'cpf_cnpj' => 'Este CPF/CNPJ já está cadastrado em outra unidade.',
            'registered_elsewhere',
        ]);

    $this->post(route('tenants.link'), ['cpf_cnpj' => $tenant->cpf_cnpj])
        ->assertRedirect(route('tenants.index', ['show' => $tenant->id]));

    expect($tenant->branches()->pluck('branches.id')->sort()->values()->all())
        ->toBe(collect([$ouricuriLease->property->branch_id, $araripina->id])->sort()->values()->all());
});

test('documents already registered in a visible tenant keep the usual message', function () {
    ['user' => $user, 'ouricuriLease' => $ouricuriLease] = branchScenario();

    $this->actingAs($user)
        ->post(route('tenants.store'), branchTenantPayload(['cpf_cnpj' => $ouricuriLease->tenant->cpf_cnpj]))
        ->assertSessionHasErrors(['cpf_cnpj' => 'Já existe um inquilino com este CPF/CNPJ.'])
        ->assertSessionDoesntHaveErrors('registered_elsewhere');
});

test('leases cannot be created for properties of branches out of view', function () {
    ['user' => $user, 'account' => $account, 'ouricuri' => $ouricuri, 'araripinaLease' => $araripinaLease] = branchScenario();
    $tenant = Tenant::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->withSession([User::SELECTED_BRANCH_SESSION_KEY => $ouricuri->id])
        ->post(route('leases.store'), branchLeasePayload($araripinaLease->property, $tenant))
        ->assertSessionHasErrors('property_id');
});
