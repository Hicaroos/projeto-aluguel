<?php

use App\Enums\Role;
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

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
});

/**
 * An agency with a rented property in each of its two branches.
 *
 * @return array{account: Account, ouricuri: Branch, araripina: Branch, ouricuriLease: Lease, araripinaLease: Lease, payment: Payment}
 */
function permissionScenario(): array
{
    $account = Account::factory()->agency()->create();
    User::factory()->for($account, 'account')->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $ouricuri = Branch::factory()->for($account, 'account')->create(['name' => 'Ouricuri']);
    $araripina = Branch::factory()->for($account, 'account')->create(['name' => 'Araripina']);

    $leaseIn = fn (Branch $branch): Lease => Lease::factory()->for($account, 'account')->create([
        'property_id' => Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['branch_id' => $branch->id])->id,
        'tenant_id' => Tenant::factory()->for($account, 'account')->create()->id,
        'amount' => 1000,
    ])->load(['property', 'tenant']);

    $ouricuriLease = $leaseIn($ouricuri);

    return [
        'account' => $account,
        'ouricuri' => $ouricuri,
        'araripina' => $araripina,
        'ouricuriLease' => $ouricuriLease,
        'araripinaLease' => $leaseIn($araripina),
        'payment' => Payment::factory()->for($ouricuriLease, 'lease')->create([
            'reference_month' => '2026-10-01',
            'due_date' => '2026-10-10',
            'amount' => 1000,
        ]),
    ];
}

/**
 * A team member of the agency with the given role, working in the given branches.
 *
 * @param  array<int, Branch>  $branches
 */
function teamMember(Account $account, Role $role, array $branches = []): User
{
    $member = User::factory()->for($account, 'account')->withRole($role)->create();
    $member->branches()->attach(collect($branches)->pluck('id'));

    return $member;
}

test('each role shares what it may do with the pages', function (Role $role, array $can) {
    ['account' => $account, 'ouricuri' => $ouricuri] = permissionScenario();

    $this->actingAs(teamMember($account, $role, [$ouricuri]))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.can', $can));
})->with([
    'admin' => [Role::Admin, ['manageAgency' => true, 'manageRentals' => true, 'registerReceipts' => true, 'manageFinance' => true]],
    'general' => [Role::General, ['manageAgency' => false, 'manageRentals' => true, 'registerReceipts' => true, 'manageFinance' => true]],
    'agent' => [Role::Agent, ['manageAgency' => false, 'manageRentals' => true, 'registerReceipts' => true, 'manageFinance' => false]],
    'finance' => [Role::Finance, ['manageAgency' => false, 'manageRentals' => false, 'registerReceipts' => true, 'manageFinance' => true]],
]);

test('agents handle rentals and receive payments, but not the rest of the finances', function () {
    ['account' => $account, 'ouricuri' => $ouricuri, 'ouricuriLease' => $lease, 'payment' => $payment] = permissionScenario();
    $agent = teamMember($account, Role::Agent, [$ouricuri]);

    $this->actingAs($agent)
        ->put(route('tenants.update', $lease->tenant), ['name' => 'Novo Nome', 'cpf_cnpj' => $lease->tenant->cpf_cnpj, 'phone' => '87999999999'])
        ->assertSessionHasNoErrors();

    $this->actingAs($agent)
        ->post(route('payments.receipts.store', $payment), ['amount' => '1000.00', 'date' => '2026-10-12', 'payment_method' => 'cash'])
        ->assertSessionHasNoErrors();

    $this->actingAs($agent)->get(route('payments.index'))->assertOk();
    $this->actingAs($agent)->get(route('expenses.index'))->assertForbidden();
    $this->actingAs($agent)->delete(route('receipts.destroy', $payment->receipts()->sole()))->assertForbidden();
    $this->actingAs($agent)->post(route('payments.store'), ['lease_id' => $lease->id])->assertForbidden();

    $this->actingAs($agent)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('stats.netIncome')
            ->missing('stats.expensesPaid')
        );
});

test('finance only looks at rentals while handling all the money', function () {
    ['account' => $account, 'ouricuri' => $ouricuri, 'ouricuriLease' => $lease] = permissionScenario();
    $finance = teamMember($account, Role::Finance, [$ouricuri]);

    $this->actingAs($finance)->get(route('properties.index'))->assertOk();
    $this->actingAs($finance)->get(route('leases.index'))->assertOk();
    $this->actingAs($finance)->get(route('expenses.index'))->assertOk();
    $this->actingAs($finance)->put(route('tenants.update', $lease->tenant), ['name' => 'X'])->assertForbidden();
    $this->actingAs($finance)->delete(route('leases.destroy', $lease))->assertForbidden();
    $this->actingAs($finance)->get(route('contract-templates.index'))->assertForbidden();

    $this->actingAs($finance)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('stats.netIncome'));
});

test('general members do everything but manage the agency', function () {
    ['account' => $account, 'ouricuri' => $ouricuri] = permissionScenario();
    $general = teamMember($account, Role::General, [$ouricuri]);

    $this->actingAs($general)->get(route('expenses.index'))->assertOk();
    $this->actingAs($general)->get(route('contract-templates.index'))->assertOk();
    $this->actingAs($general)->get(route('branches.index'))->assertForbidden();
    $this->actingAs($general)->get(route('agency.edit'))->assertForbidden();
});

test('members only see the branches they work in', function () {
    ['account' => $account, 'ouricuri' => $ouricuri, 'araripina' => $araripina, 'ouricuriLease' => $ouricuriLease, 'araripinaLease' => $araripinaLease] = permissionScenario();
    Expense::factory()->for($araripinaLease->property, 'property')->create(['account_id' => $account->id]);
    $general = teamMember($account, Role::General, [$ouricuri]);

    $this->actingAs($general)
        ->get(route('properties.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.id', $ouricuriLease->property_id)
            ->where('branchSelector.branches', [['id' => $ouricuri->id, 'name' => 'Ouricuri']])
            ->has('branches', 1)
        );

    $this->actingAs($general)
        ->get(route('leases.index'))
        ->assertInertia(fn (Assert $page) => $page->has('leases.data', 1));

    $this->actingAs($general)
        ->get(route('expenses.index'))
        ->assertInertia(fn (Assert $page) => $page->has('expenses.data', 0));

    $this->actingAs($general)->get(route('lease-documents.show', 999))->assertNotFound();
    $this->actingAs($general)->get(route('leases.contract', $araripinaLease))->assertNotFound();

    $this->actingAs($general)
        ->put(route('branch-selection.update'), ['branch_id' => $araripina->id])
        ->assertSessionHasErrors('branch_id');
});

test('members cannot place properties in branches they do not work in', function () {
    ['account' => $account, 'ouricuri' => $ouricuri, 'araripina' => $araripina] = permissionScenario();
    $agent = teamMember($account, Role::Agent, [$ouricuri]);
    $owner = Owner::where('account_id', $account->id)->first();

    $payload = [
        'type' => 'house',
        'owner_id' => $owner->id,
        'zip_code' => '56200-000',
        'street' => 'Rua Nova',
        'number' => '1',
        'neighborhood' => 'Centro',
        'city' => 'Araripina',
        'state' => 'PE',
        'rent_amount' => '900.00',
        'status' => 'available',
    ];

    $this->actingAs($agent)
        ->post(route('properties.store'), [...$payload, 'branch_id' => $araripina->id])
        ->assertSessionHasErrors('branch_id');

    $this->actingAs($agent)
        ->post(route('properties.store'), [...$payload, 'branch_id' => $ouricuri->id])
        ->assertSessionHasNoErrors();
});

test('administrators see every branch', function () {
    ['account' => $account, 'ouricuri' => $ouricuri] = permissionScenario();
    $admin = teamMember($account, Role::Admin, [$ouricuri]);

    $this->actingAs($admin)
        ->get(route('properties.index'))
        ->assertInertia(fn (Assert $page) => $page->has('properties.data', 2));
});
