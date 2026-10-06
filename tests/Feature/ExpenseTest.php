<?php

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\PaymentType;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Property;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
});

/**
 * @return array{account: Account, user: User, property: Property}
 */
function expenseScenario(): array
{
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create();

    return [
        'account' => $account,
        'user' => User::factory()->for($account, 'account')->create(),
        'property' => Property::factory()->for($account, 'account')->for($owner, 'owner')->create(),
    ];
}

function validExpensePayload(Property $property, array $overrides = []): array
{
    return array_merge([
        'property_id' => $property->id,
        'type' => ExpenseType::CondoFee->value,
        'description' => 'Condomínio de outubro',
        'amount' => '650.00',
        'due_date' => '2026-10-20',
        'payment_date' => null,
    ], $overrides);
}

test('index lists the account expenses due in the current month with a summary', function () {
    ['account' => $account, 'user' => $user, 'property' => $property] = expenseScenario();
    Expense::factory()->for($account, 'account')->for($property)->create(['due_date' => '2026-10-05', 'amount' => 100]);
    Expense::factory()->paid()->for($account, 'account')->for($property)->create(['due_date' => '2026-10-10', 'amount' => 300]);
    Expense::factory()->for($account, 'account')->for($property)->create(['due_date' => '2026-10-25', 'amount' => 50]);
    Expense::factory()->for($account, 'account')->for($property)->create(['due_date' => '2026-11-05']);
    Expense::factory()->create(['due_date' => '2026-10-05']);

    $this->actingAs($user)
        ->get(route('expenses.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('expenses/Index')
            ->where('filters.month', '2026-10')
            ->has('expenses.data', 3)
            ->where('summary.total', 450)
            ->where('summary.paid', 300)
            ->where('summary.pending', 150)
            ->where('summary.overdue', 100)
            ->where('summary.overdue_count', 1)
            ->has('properties', 1)
        );
});

test('index filters expenses by month and overdue status', function () {
    ['account' => $account, 'user' => $user, 'property' => $property] = expenseScenario();
    Expense::factory()->for($account, 'account')->for($property)->create(['due_date' => '2026-10-05']);
    Expense::factory()->for($account, 'account')->for($property)->create(['due_date' => '2026-10-25']);
    Expense::factory()->for($account, 'account')->for($property)->create(['due_date' => '2026-11-05']);

    $this->actingAs($user)
        ->get(route('expenses.index', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page->has('expenses.data', 1));

    $this->actingAs($user)
        ->get(route('expenses.index', ['status' => 'overdue']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('expenses.data', 1)
            ->where('expenses.data.0.due_date', '2026-10-05')
        );
});

test('an expense is created as pending, or as paid when it has a payment date', function () {
    ['account' => $account, 'user' => $user, 'property' => $property] = expenseScenario();

    $this->actingAs($user)
        ->post(route('expenses.store'), validExpensePayload($property))
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('expenses.store'), validExpensePayload($property, ['type' => 'property_tax', 'payment_date' => '2026-10-10']))
        ->assertSessionHasNoErrors();

    expect(Expense::where('account_id', $account->id)->where('type', 'condo_fee')->first()->status)->toBe(ExpenseStatus::Pending)
        ->and(Expense::where('type', 'property_tax')->first()->status)->toBe(ExpenseStatus::Paid);
});

test('an expense validates its type, property and payment date', function () {
    ['user' => $user, 'property' => $property] = expenseScenario();
    $otherProperty = Property::factory()->create();

    $this->actingAs($user)
        ->post(route('expenses.store'), validExpensePayload($property, ['type' => 'other', 'description' => '']))
        ->assertSessionHasErrors('description');

    $this->actingAs($user)
        ->post(route('expenses.store'), validExpensePayload($otherProperty, ['payment_date' => '2026-10-16', 'amount' => '0']))
        ->assertSessionHasErrors(['property_id', 'payment_date', 'amount']);

    expect(Expense::count())->toBe(0);
});

test('updating an expense without a payment date moves it back to pending', function () {
    ['account' => $account, 'user' => $user, 'property' => $property] = expenseScenario();
    $expense = Expense::factory()->paid()->for($account, 'account')->for($property)->create();

    $this->actingAs($user)
        ->put(route('expenses.update', $expense), validExpensePayload($property, ['amount' => '700.00']))
        ->assertSessionHasNoErrors();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::Pending)
        ->and($expense->fresh()->payment_date)->toBeNull()
        ->and($expense->fresh()->amount)->toBe('700.00');
});

test('a pending expense can be marked as paid only once', function () {
    ['account' => $account, 'user' => $user, 'property' => $property] = expenseScenario();
    $expense = Expense::factory()->for($account, 'account')->for($property)->create();

    $this->actingAs($user)
        ->patch(route('expenses.pay', $expense), ['payment_date' => '2026-10-14'])
        ->assertSessionHasNoErrors();

    expect($expense->fresh()->status)->toBe(ExpenseStatus::Paid)
        ->and($expense->fresh()->payment_date->toDateString())->toBe('2026-10-14');

    $this->actingAs($user)
        ->patch(route('expenses.pay', $expense), ['payment_date' => '2026-10-14'])
        ->assertForbidden();
});

test('an expense can be deleted', function () {
    ['account' => $account, 'user' => $user, 'property' => $property] = expenseScenario();
    $expense = Expense::factory()->for($account, 'account')->for($property)->create();

    $this->actingAs($user)->delete(route('expenses.destroy', $expense));

    expect(Expense::count())->toBe(0);
});

test('a user cannot manage expenses from another account', function () {
    ['user' => $user, 'property' => $property] = expenseScenario();
    $otherExpense = Expense::factory()->create();

    $this->actingAs($user)->put(route('expenses.update', $otherExpense), validExpensePayload($property))->assertNotFound();
    $this->actingAs($user)->patch(route('expenses.pay', $otherExpense), ['payment_date' => '2026-10-14'])->assertNotFound();
    $this->actingAs($user)->delete(route('expenses.destroy', $otherExpense))->assertNotFound();

    expect($otherExpense->fresh()->isPending())->toBeTrue();
});

test('an expense can be charged to the tenant of a lease of the same property', function () {
    ['account' => $account, 'user' => $user, 'property' => $property] = expenseScenario();
    $lease = Lease::factory()->ended()->for($account, 'account')->for($property)->create();

    $this->actingAs($user)
        ->post(route('expenses.store'), validExpensePayload($property, [
            'type' => 'maintenance',
            'description' => 'Reparo da pintura',
            'amount' => '800.00',
            'charge_tenant' => '1',
            'charge_lease_id' => $lease->id,
        ]))
        ->assertSessionHasNoErrors();

    $expense = Expense::first();
    $charge = $expense->payment;

    expect($charge)->not->toBeNull()
        ->and($charge->lease_id)->toBe($lease->id)
        ->and($charge->type)->toBe(PaymentType::Extra)
        ->and($charge->description)->toBe('Reparo da pintura')
        ->and($charge->amount)->toBe('800.00');
});

test('an expense can only be charged to a lease of its own property', function () {
    ['account' => $account, 'user' => $user, 'property' => $property] = expenseScenario();
    $otherPropertyLease = Lease::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->post(route('expenses.store'), validExpensePayload($property, [
            'charge_tenant' => '1',
            'charge_lease_id' => $otherPropertyLease->id,
        ]))
        ->assertSessionHasErrors('charge_lease_id');

    $this->actingAs($user)
        ->post(route('expenses.store'), validExpensePayload($property, ['charge_tenant' => '0', 'charge_lease_id' => 999]))
        ->assertSessionHasNoErrors();

    expect(Expense::count())->toBe(1)
        ->and(Expense::first()->payment_id)->toBeNull();
});
