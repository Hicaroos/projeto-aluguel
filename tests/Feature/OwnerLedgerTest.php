<?php

use App\Enums\ExpenseStatus;
use App\Enums\ExpenseType;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\LeaseRenewal;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-03-20 09:00:00');
});

/**
 * An agency with known payout defaults, and a lease of R$ 1.000 from January 2026, due on the 10th.
 *
 * @return array{account: Account, user: User, owner: Owner, property: Property, lease: Lease}
 */
function ledgerScenario(array $accountRules = [], string $startDate = '2026-01-01'): array
{
    $account = Account::factory()->agency()->create([
        'admin_fee_percent' => 10,
        'intermediation_fee_percent' => 100,
        'renewal_fee_percent' => 50,
        'admin_fee_with_intermediation' => false,
        'admin_fee_on_extra_charges' => false,
        'extra_charges_to_owner' => true,
        'late_charges_owner_percent' => 100,
        'payout_mode' => 'received',
        'payout_schedule' => 'days_after',
        'payout_days' => 5,
        'payout_day' => 10,
        ...$accountRules,
    ]);
    $branch = Branch::factory()->for($account, 'account')->create();
    $owner = Owner::factory()->for($account, 'account')->create(['name' => 'Maria']);
    $property = Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['branch_id' => $branch->id]);

    return [
        'account' => $account,
        'user' => User::factory()->for($account, 'account')->create(),
        'owner' => $owner,
        'property' => $property,
        'lease' => Lease::factory()->for($account, 'account')->for($property, 'property')->create([
            'tenant_id' => Tenant::factory()->for($account, 'account')->create()->id,
            'start_date' => $startDate,
            'end_date' => '2028-12-31',
            'amount' => 1000,
            'due_day' => 10,
        ]),
    ];
}

function ledgerRent(Lease $lease, string $month, float $amount = 1000): Payment
{
    return Payment::factory()->for($lease)->create([
        'account_id' => $lease->account_id,
        'type' => PaymentType::Rent,
        'reference_month' => "{$month}-01",
        'due_date' => "{$month}-10",
        'amount' => $amount,
    ]);
}

/**
 * Get the owner's entries as [type, amount] pairs, oldest first.
 *
 * @return list<array{0: string, 1: string}>
 */
function ledgerOf(Owner $owner): array
{
    return OwnerLedgerEntry::withoutGlobalScopes()
        ->where('owner_id', $owner->id)
        ->orderBy('id')
        ->get()
        ->map(fn (OwnerLedgerEntry $entry): array => [$entry->type->value, $entry->amount])
        ->all();
}

function receive(User $user, Payment $payment, array $attributes = []): void
{
    test()->actingAs($user)
        ->post(route('payments.receipts.store', $payment), [
            'amount' => $payment->amount,
            'date' => $payment->due_date->toDateString(),
            'payment_method' => 'pix',
            ...$attributes,
        ])
        ->assertSessionHasNoErrors();
}

test('rent received is credited to the owner less the admin fee, payable some days later', function () {
    ['user' => $user, 'owner' => $owner, 'lease' => $lease] = ledgerScenario();

    receive($user, ledgerRent($lease, '2026-02'));

    expect(ledgerOf($owner))->toBe([['rent', '1000.00'], ['admin_fee', '-100.00']]);

    $rent = OwnerLedgerEntry::where('type', LedgerEntryType::Rent)->sole();

    expect($rent->description)->toBe('Aluguel de fevereiro de 2026')
        ->and($rent->occurred_on->toDateString())->toBe('2026-02-10')
        ->and($rent->available_on->toDateString())->toBe('2026-02-15')
        ->and($rent->property_id)->toBe($lease->property_id);
});

test('the first rent carries the intermediation fee once, without the admin fee unless the rules say so', function () {
    ['user' => $user, 'owner' => $owner, 'lease' => $lease] = ledgerScenario();
    $january = ledgerRent($lease, '2026-01');

    receive($user, $january, ['amount' => 600]);
    receive($user, $january, ['amount' => 400]);

    expect(ledgerOf($owner))->toBe([['rent', '600.00'], ['intermediation_fee', '-1000.00'], ['rent', '400.00']]);

    ['user' => $user, 'owner' => $owner, 'lease' => $lease] = ledgerScenario(['intermediation_fee_percent' => 50, 'admin_fee_with_intermediation' => true]);

    receive($user, ledgerRent($lease, '2026-01'));

    expect(ledgerOf($owner))->toBe([['rent', '1000.00'], ['intermediation_fee', '-500.00'], ['admin_fee', '-100.00']]);
});

test('no intermediation fee is charged when the agency charges none', function () {
    ['user' => $user, 'owner' => $owner, 'lease' => $lease] = ledgerScenario(['intermediation_fee_percent' => 0]);

    receive($user, ledgerRent($lease, '2026-01'));

    expect(ledgerOf($owner))->toBe([['rent', '1000.00'], ['admin_fee', '-100.00']]);
});

test('the first rent after a renewal carries the renewal fee', function () {
    $this->travelTo('2027-01-20 09:00:00');
    ['user' => $user, 'owner' => $owner, 'lease' => $lease] = ledgerScenario(startDate: '2025-01-01');
    LeaseRenewal::create(['lease_id' => $lease->id, 'previous_end_date' => '2026-12-31', 'new_end_date' => '2028-12-31']);

    receive($user, ledgerRent($lease, '2026-12'));
    receive($user, ledgerRent($lease, '2027-01'));

    expect(ledgerOf($owner))->toBe([
        ['rent', '1000.00'], ['admin_fee', '-100.00'],
        ['rent', '1000.00'], ['renewal_fee', '-500.00'],
    ]);
});

test('co-owners get their share of everything', function () {
    ['account' => $account, 'user' => $user, 'owner' => $maria, 'property' => $property, 'lease' => $lease] = ledgerScenario();
    $joao = Owner::factory()->for($account, 'account')->create();
    $property->owners()->sync([$maria->id => ['share_percent' => 60], $joao->id => ['share_percent' => 40]]);

    receive($user, ledgerRent($lease, '2026-02'));

    expect(ledgerOf($maria))->toBe([['rent', '600.00'], ['admin_fee', '-60.00']])
        ->and(ledgerOf($joao))->toBe([['rent', '400.00'], ['admin_fee', '-40.00']])
        ->and(OwnerLedgerEntry::where('owner_id', $joao->id)->first()->share_percent)->toBe('40.00');
});

test('the lease rules come before the owner rules and the agency defaults', function () {
    ['user' => $user, 'owner' => $owner, 'lease' => $lease] = ledgerScenario();
    $owner->update(['admin_fee_percent' => 8]);

    receive($user, ledgerRent($lease, '2026-02'));
    $lease->update(['admin_fee_percent' => 7]);
    receive($user, ledgerRent($lease, '2026-03'));

    expect(ledgerOf($owner))->toBe([
        ['rent', '1000.00'], ['admin_fee', '-80.00'],
        ['rent', '1000.00'], ['admin_fee', '-70.00'],
    ]);
});

test('late charges go to whom the rules or the receipt say', function () {
    ['user' => $user, 'owner' => $owner, 'lease' => $lease] = ledgerScenario(['admin_fee_percent' => 0]);
    $lateCharges = ['date' => '2026-02-20', 'late_fee_amount' => '100', 'interest_amount' => '10'];

    receive($user, ledgerRent($lease, '2026-02'), $lateCharges);
    $lease->update(['late_charges_owner_percent' => 50]);
    receive($user, ledgerRent($lease, '2026-03'), $lateCharges);
    receive($user, ledgerRent($lease, '2026-04'), [...$lateCharges, 'late_charges_owner_percent' => '0']);

    expect(ledgerOf($owner))->toBe([
        ['rent', '1000.00'], ['late_charges', '110.00'],
        ['rent', '1000.00'], ['late_charges', '55.00'],
        ['rent', '1000.00'],
    ]);
});

test('extra charges go to the owner unless they stay with the agency', function () {
    ['user' => $user, 'owner' => $owner, 'lease' => $lease] = ledgerScenario(['admin_fee_on_extra_charges' => true]);

    $this->actingAs($user)->post(route('payments.store'), ['lease_id' => $lease->id, 'description' => 'Conserto da pia', 'amount' => '200', 'due_date' => '2026-03-15']);
    $this->actingAs($user)->post(route('payments.store'), ['lease_id' => $lease->id, 'description' => 'Taxa de segunda via', 'amount' => '20', 'due_date' => '2026-03-15', 'pass_to_owner' => '0']);

    Payment::where('type', PaymentType::Extra)->get()->each(fn (Payment $charge) => receive($user, $charge));

    expect(ledgerOf($owner))->toBe([['extra_charge', '200.00'], ['admin_fee', '-20.00']])
        ->and(OwnerLedgerEntry::where('type', LedgerEntryType::ExtraCharge)->sole()->description)->toBe('Conserto da pia');
});

test('expenses the owner bears are taken from them once paid', function () {
    ['account' => $account, 'user' => $user, 'owner' => $owner, 'property' => $property] = ledgerScenario();
    $expense = Expense::factory()->for($account, 'account')->for($property, 'property')->create([
        'type' => ExpenseType::PropertyTax,
        'description' => 'Parcela 3',
        'amount' => 150,
        'status' => ExpenseStatus::Pending,
        'payment_date' => null,
    ]);
    Expense::factory()->for($account, 'account')->for($property, 'property')->create([
        'charged_to' => 'agency',
        'amount' => 80,
        'status' => ExpenseStatus::Paid,
        'payment_date' => '2026-03-01',
    ]);

    expect(ledgerOf($owner))->toBe([]);

    $this->actingAs($user)->patch(route('expenses.pay', $expense), ['payment_date' => '2026-03-05'])->assertSessionHasNoErrors();

    expect(ledgerOf($owner))->toBe([['expense', '-150.00']])
        ->and(OwnerLedgerEntry::sole()->description)->toBe('IPTU: Parcela 3');

    $this->actingAs($user)->delete(route('expenses.destroy', $expense));

    expect(ledgerOf($owner))->toBe([]);
});

test('removing a receipt registered by mistake removes what it posted', function () {
    ['user' => $user, 'owner' => $owner, 'lease' => $lease] = ledgerScenario();
    receive($user, ledgerRent($lease, '2026-02'), ['date' => '2026-02-20', 'late_fee_amount' => '100']);

    $this->actingAs($user)->delete(route('receipts.destroy', Receipt::sole()))->assertSessionHasNoErrors();

    expect(ledgerOf($owner))->toBe([]);
});

test('guaranteed rent is credited on the agreed date even when the tenant has not paid', function () {
    ['user' => $user, 'owner' => $owner, 'lease' => $lease] = ledgerScenario(['payout_mode' => 'guaranteed']);
    $march = ledgerRent($lease, '2026-03');
    $april = ledgerRent($lease, '2026-04');

    $this->artisan('app:credit-guaranteed-rent')->assertSuccessful();

    expect(ledgerOf($owner))->toBe([['rent', '1000.00'], ['admin_fee', '-100.00']]);

    $credit = OwnerLedgerEntry::where('type', LedgerEntryType::Rent)->sole();

    expect($credit->payment_id)->toBe($march->id)
        ->and($credit->is_guaranteed)->toBeTrue()
        ->and($credit->available_on->toDateString())->toBe('2026-03-15')
        ->and($credit->description)->toBe('Aluguel garantido de março de 2026');

    $this->artisan('app:credit-guaranteed-rent');
    receive($user, $march, ['date' => '2026-03-20', 'late_fee_amount' => '50']);

    expect(ledgerOf($owner))->toBe([['rent', '1000.00'], ['admin_fee', '-100.00'], ['late_charges', '50.00']])
        ->and($april->refresh()->status)->toBe(PaymentStatus::Pending);
});

test('guaranteed rent paid early is credited for the agreed date', function () {
    ['user' => $user, 'owner' => $owner, 'lease' => $lease] = ledgerScenario(['payout_mode' => 'guaranteed', 'payout_schedule' => 'monthly_day', 'payout_day' => 25]);

    receive($user, ledgerRent($lease, '2026-04'), ['date' => '2026-03-20']);

    expect(ledgerOf($owner))->toBe([['rent', '1000.00'], ['admin_fee', '-100.00']])
        ->and(OwnerLedgerEntry::where('type', LedgerEntryType::Rent)->sole()->available_on->toDateString())->toBe('2026-04-25');
});

test('single owners receive their rent themselves, so nothing is posted', function () {
    $account = Account::factory()->create();
    $lease = Lease::factory()->for($account, 'account')->create(['amount' => 1000]);

    receive(User::factory()->for($account, 'account')->create(), ledgerRent($lease, '2026-02'));
    $this->artisan('app:credit-guaranteed-rent');

    expect(OwnerLedgerEntry::count())->toBe(0);
});

test('a down payment received by the agency goes to the seller less the commission', function () {
    ['user' => $user, 'owner' => $owner, 'property' => $property] = ledgerScenario();
    $property->update(['for_sale' => true, 'sale_price' => 300000]);

    $this->actingAs($user)
        ->post(route('sales.store'), [
            'property_id' => $property->id,
            'buyer_name' => 'Carlos Comprador',
            'price' => '300000',
            'sold_on' => '2026-03-18',
            'down_payment' => '30000',
            'down_payment_received_by_agency' => '1',
            'commission_percent' => '6',
            'rental_outcome' => 'keep',
        ])
        ->assertSessionHasNoErrors();

    expect(ledgerOf($owner))->toBe([['sale_down_payment', '30000.00'], ['sale_commission', '-18000.00']]);

    $sale = Sale::sole();

    $this->actingAs($user)
        ->put(route('sales.update', $sale), [
            'buyer_name' => 'Carlos Comprador',
            'price' => '300000',
            'sold_on' => '2026-03-18',
            'down_payment' => '30000',
            'down_payment_received_by_agency' => '0',
            'commission_percent' => '6',
        ])
        ->assertSessionHasNoErrors();

    expect(ledgerOf($owner))->toBe([]);
});
