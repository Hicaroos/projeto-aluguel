<?php

use App\Enums\GuaranteeType;
use App\Enums\LeaseStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Account;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-20 09:00:00');
});

/**
 * @return array{user: User, lease: Lease}
 */
function depositScenario(array $leaseAttributes = []): array
{
    $account = Account::factory()->create();

    return [
        'user' => User::factory()->for($account, 'account')->create(),
        'lease' => Lease::factory()->for($account, 'account')->create([
            'start_date' => '2025-10-01',
            'end_date' => '2026-09-30',
            'amount' => 1000,
            'guarantee_type' => GuaranteeType::Deposit,
            'deposit_amount' => 3000,
            'status' => LeaseStatus::Ended,
            ...$leaseAttributes,
        ]),
    ];
}

function rentPayment(Lease $lease, string $month, float $amount = 1000): Payment
{
    return Payment::factory()->for($lease)->for($lease->account, 'account')->create([
        'type' => PaymentType::Rent,
        'reference_month' => "{$month}-01",
        'due_date' => "{$month}-10",
        'amount' => $amount,
        'status' => PaymentStatus::Pending,
    ]);
}

test('the deposit pays the chosen open payments and the exit deductions, and the rest is refunded', function () {
    ['user' => $user, 'lease' => $lease] = depositScenario();
    $september = rentPayment($lease, '2026-09');

    $this->actingAs($user)
        ->post(route('leases.deposit-settlement.store', $lease), [
            'payment_ids' => [$september->id],
            'deductions' => [['description' => 'Pintura', 'amount' => '600.00']],
            'settled_on' => '2026-10-20',
        ])
        ->assertSessionHasNoErrors();

    $painting = $lease->payments()->where('type', PaymentType::Extra)->sole();

    expect($september->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($september->receipts()->sole()->payment_method)->toBe(PaymentMethod::Deposit)
        ->and($painting->description)->toBe('Pintura')
        ->and($painting->status)->toBe(PaymentStatus::Paid)
        ->and($lease->fresh()->deposit_settled_on->toDateString())->toBe('2026-10-20')
        ->and($lease->fresh()->deposit_refunded_amount)->toBe('1400.00')
        ->and($lease->fresh()->canSettleDeposit())->toBeFalse();
});

test('debts above the deposit use it up, oldest first, and the rest stays open', function () {
    ['user' => $user, 'lease' => $lease] = depositScenario(['deposit_amount' => 1500]);
    $august = rentPayment($lease, '2026-08');
    $september = rentPayment($lease, '2026-09');

    $this->actingAs($user)
        ->post(route('leases.deposit-settlement.store', $lease), [
            'payment_ids' => [$september->id, $august->id],
            'settled_on' => '2026-10-20',
        ])
        ->assertSessionHasNoErrors();

    expect($august->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($september->fresh()->status)->toBe(PaymentStatus::Partial)
        ->and($september->fresh()->remainingAmount())->toBe(500.0)
        ->and($lease->fresh()->deposit_refunded_amount)->toBe('0.00');
});

test('payments left unchecked are not paid out of the deposit', function () {
    ['user' => $user, 'lease' => $lease] = depositScenario();
    $september = rentPayment($lease, '2026-09');

    $this->actingAs($user)
        ->post(route('leases.deposit-settlement.store', $lease), ['settled_on' => '2026-10-20'])
        ->assertSessionHasNoErrors();

    expect($september->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($lease->fresh()->deposit_refunded_amount)->toBe('3000.00');
});

test('the deposit can only be settled once, for finished leases guaranteed by a deposit', function () {
    ['user' => $user, 'lease' => $activeLease] = depositScenario(['status' => LeaseStatus::Active]);
    $withoutDeposit = depositScenario(['guarantee_type' => GuaranteeType::None, 'deposit_amount' => null]);
    $settled = depositScenario(['deposit_settled_on' => '2026-10-01', 'deposit_refunded_amount' => 3000]);

    $this->actingAs($user)
        ->post(route('leases.deposit-settlement.store', $activeLease), ['settled_on' => '2026-10-20'])
        ->assertForbidden();

    $this->actingAs($withoutDeposit['user'])
        ->post(route('leases.deposit-settlement.store', $withoutDeposit['lease']), ['settled_on' => '2026-10-20'])
        ->assertForbidden();

    $this->actingAs($settled['user'])
        ->post(route('leases.deposit-settlement.store', $settled['lease']), ['settled_on' => '2026-10-20'])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('leases.deposit-settlement.store', $settled['lease']), ['settled_on' => '2026-10-20'])
        ->assertNotFound();
});

test('a settlement validates the payments, the deductions and the date', function () {
    ['user' => $user, 'lease' => $lease] = depositScenario();
    $otherLeasePayment = rentPayment(depositScenario()['lease'], '2026-09');

    $this->actingAs($user)
        ->post(route('leases.deposit-settlement.store', $lease), [
            'payment_ids' => [$otherLeasePayment->id],
            'deductions' => [['description' => 'Reforma', 'amount' => '3000.01']],
            'settled_on' => '2026-10-21',
        ])
        ->assertSessionHasErrors(['payment_ids.0', 'deductions', 'settled_on']);

    expect($lease->fresh()->isDepositSettled())->toBeFalse();
});

test('the leases list sends the open payments of a lease whose deposit can be settled', function () {
    ['user' => $user, 'lease' => $lease] = depositScenario();
    $september = rentPayment($lease, '2026-09');

    $this->actingAs($user)
        ->get(route('leases.index', ['show' => $lease->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.open_payments.0.id', $september->id)
            ->where('selected.deposit_settled_on', null)
        );
});
