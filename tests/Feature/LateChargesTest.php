<?php

use App\Actions\Payments\CalculateLateCharges;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Account;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-30 09:00:00');
});

/**
 * @return array{user: User, lease: Lease, payment: Payment}
 */
function lateChargesScenario(): array
{
    $account = Account::factory()->create();
    $lease = Lease::factory()->for($account, 'account')->create([
        'start_date' => '2026-01-01',
        'end_date' => '2028-06-30',
        'due_day' => 10,
        'amount' => 2000,
        'late_fee_percent' => 10,
        'monthly_interest_percent' => 1,
    ]);

    return [
        'user' => User::factory()->for($account, 'account')->create(),
        'lease' => $lease,
        'payment' => Payment::factory()->for($lease)->for($account, 'account')->create([
            'reference_month' => '2026-10-01',
            'due_date' => '2026-10-10',
            'amount' => 2000,
        ]),
    ];
}

function lateCharges(Payment $payment, float $amount, string $paidOn): array
{
    return app(CalculateLateCharges::class)->handle($payment->fresh(), $amount, CarbonImmutable::parse($paidOn));
}

test('a rent paid on or before the due date has no late charges', function () {
    ['payment' => $payment] = lateChargesScenario();

    expect(lateCharges($payment, 2000, '2026-10-10'))->toBe(['days_late' => 0, 'late_fee' => 0.0, 'interest' => 0.0])
        ->and(lateCharges($payment, 2000, '2026-10-05'))->toBe(['days_late' => 0, 'late_fee' => 0.0, 'interest' => 0.0]);
});

test('the late fee is charged once and interest is pro rata per day', function (string $paidOn, int $days, float $fee, float $interest) {
    ['payment' => $payment] = lateChargesScenario();

    expect(lateCharges($payment, 2000, $paidOn))->toBe(['days_late' => $days, 'late_fee' => $fee, 'interest' => $interest]);
})->with([
    '1 dia' => ['2026-10-11', 1, 200.0, 0.67],
    '20 dias' => ['2026-10-30', 20, 200.0, 13.33],
]);

test('late charges of a partial payment are calculated on the amount paid', function () {
    ['payment' => $payment] = lateChargesScenario();

    expect(lateCharges($payment, 500, '2026-10-30'))->toBe(['days_late' => 20, 'late_fee' => 50.0, 'interest' => 3.33]);
});

test('extra charges have no late charges', function () {
    ['lease' => $lease] = lateChargesScenario();
    $extra = Payment::factory()->for($lease)->create([
        'type' => PaymentType::Extra,
        'reference_month' => null,
        'description' => 'Reparo',
        'due_date' => '2026-10-01',
        'amount' => 300,
    ]);

    expect(lateCharges($extra, 300, '2026-10-30'))->toBe(['days_late' => 29, 'late_fee' => 0.0, 'interest' => 0.0]);
});

test('a receipt stores the late charges while the balance only counts the rent', function () {
    ['user' => $user, 'payment' => $payment] = lateChargesScenario();

    $this->actingAs($user)
        ->post(route('payments.receipts.store', $payment), [
            'amount' => '2000.00',
            'late_fee_amount' => '200.00',
            'interest_amount' => '13.33',
            'date' => '2026-10-30',
            'payment_method' => 'pix',
        ])
        ->assertSessionHasNoErrors();

    $receipt = $payment->receipts()->sole();

    expect($receipt->late_fee_amount)->toBe('200.00')
        ->and($receipt->interest_amount)->toBe('13.33')
        ->and($receipt->totalAmount())->toBe(2213.33)
        ->and($receipt->amountInWords())->toBe('dois mil duzentos e treze reais e trinta e três centavos')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

test('late charges can be waived but not negative', function () {
    ['user' => $user, 'payment' => $payment] = lateChargesScenario();

    $this->actingAs($user)
        ->post(route('payments.receipts.store', $payment), [
            'amount' => '2000.00',
            'late_fee_amount' => '-1',
            'interest_amount' => '-1',
            'date' => '2026-10-30',
            'payment_method' => 'pix',
        ])
        ->assertSessionHasErrors(['late_fee_amount', 'interest_amount']);

    $this->actingAs($user)
        ->post(route('payments.receipts.store', $payment), [
            'amount' => '2000.00',
            'date' => '2026-10-30',
            'payment_method' => 'pix',
        ])
        ->assertSessionHasNoErrors();

    expect($payment->receipts()->sole()->totalAmount())->toBe(2000.0);
});

test('late charges sent for an extra charge are ignored', function () {
    ['user' => $user, 'lease' => $lease] = lateChargesScenario();
    $extra = Payment::factory()->for($lease)->for($lease->account, 'account')->create([
        'type' => PaymentType::Extra,
        'reference_month' => null,
        'description' => 'Reparo',
        'due_date' => '2026-10-01',
        'amount' => 300,
    ]);

    $this->actingAs($user)
        ->post(route('payments.receipts.store', $extra), [
            'amount' => '300.00',
            'late_fee_amount' => '30.00',
            'interest_amount' => '3.00',
            'date' => '2026-10-30',
            'payment_method' => 'pix',
        ])
        ->assertSessionHasNoErrors();

    expect($extra->receipts()->sole()->totalAmount())->toBe(300.0);
});

test('the payments list shows the received late charges and the updated amount of overdue rent', function () {
    ['user' => $user, 'lease' => $lease, 'payment' => $payment] = lateChargesScenario();
    $september = Payment::factory()->for($lease)->for($lease->account, 'account')->create([
        'reference_month' => '2026-09-01',
        'due_date' => '2026-10-05',
        'amount' => 2000,
    ]);
    Receipt::factory()->for($september)->create(['amount' => 2000, 'late_fee_amount' => 200, 'interest_amount' => 6.67]);
    $september->refreshStatus();

    $this->actingAs($user)
        ->get(route('payments.index', ['month' => '2026-10', 'sort' => 'due_date', 'direction' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.received', 2000)
            ->where('summary.charges', 206.67)
            ->where('payments.data.0.late_charges_today.late_fee', 0)
            ->where('payments.data.1.id', $payment->id)
            ->where('payments.data.1.late_charges_today.days_late', 20)
            ->where('payments.data.1.late_charges_today.late_fee', 200)
            ->where('payments.data.1.late_charges_today.interest', 13.33)
        );
});
