<?php

use App\Enums\PaymentStatus;
use App\Models\Account;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
});

/**
 * @return array{user: User, lease: Lease}
 */
function paymentScenario(): array
{
    $account = Account::factory()->create();

    return [
        'user' => User::factory()->for($account, 'account')->create(),
        'lease' => Lease::factory()->for($account, 'account')->create([
            'start_date' => '2026-01-01',
            'end_date' => '2028-06-30',
            'due_day' => 10,
            'amount' => 1000,
        ]),
    ];
}

function receiptPayload(array $overrides = []): array
{
    return array_merge([
        'amount' => '1000.00',
        'date' => '2026-10-12',
        'payment_method' => 'pix',
        'notes' => null,
    ], $overrides);
}

test('index generates the missing payments and lists the current month', function () {
    ['user' => $user] = paymentScenario();

    $this->actingAs($user)
        ->get(route('payments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('payments/Index')
            ->where('filters.month', '2026-10')
            ->has('payments.data', 1)
            ->where('payments.data.0.due_date', '2026-10-10')
        );

    expect(Payment::count())->toBe(2);
});

test('index only lists payments of the authenticated account', function () {
    ['user' => $user] = paymentScenario();
    Payment::factory()->create(['due_date' => '2026-10-20']);

    $this->actingAs($user)
        ->get(route('payments.index'))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1));
});

test('index filters payments by month', function () {
    ['user' => $user] = paymentScenario();

    $this->actingAs($user)
        ->get(route('payments.index', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.month', '2026-11')
            ->has('payments.data', 1)
            ->where('payments.data.0.due_date', '2026-11-10')
        );
});

test('index filters overdue payments', function () {
    ['user' => $user, 'lease' => $lease] = paymentScenario();
    Payment::factory()->for($lease)->create(['reference_month' => '2026-09-01', 'due_date' => '2026-10-01']);

    $this->actingAs($user)
        ->get(route('payments.index', ['status' => 'overdue']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 2)
            ->where('summary.overdue', 2000)
        );
});

test('index lists every payment of a single lease', function () {
    ['user' => $user, 'lease' => $lease] = paymentScenario();

    $this->actingAs($user)
        ->get(route('payments.index', ['lease' => $lease->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.lease', $lease->id)
            ->has('payments.data', 2)
            ->where('lease.id', $lease->id)
        );
});

test('summary adds up expected, received and open amounts', function () {
    ['user' => $user, 'lease' => $lease] = paymentScenario();
    $this->actingAs($user)->get(route('payments.index'));
    Receipt::factory()->for(Payment::whereDate('due_date', '2026-10-10')->first())->create(['amount' => 400]);

    $this->actingAs($user)
        ->get(route('payments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.expected', 1000)
            ->where('summary.received', 400)
            ->where('summary.open', 600)
            ->where('summary.overdue', 600)
        );
});

test('registering receipts moves the payment to partial and then paid', function () {
    ['user' => $user, 'lease' => $lease] = paymentScenario();
    $payment = Payment::factory()->for($lease)->create();

    $this->actingAs($user)
        ->post(route('payments.receipts.store', $payment), receiptPayload(['amount' => '400.00']))
        ->assertSessionHasNoErrors();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Partial);

    $this->actingAs($user)
        ->post(route('payments.receipts.store', $payment), receiptPayload(['amount' => '600.00', 'payment_method' => 'cash']))
        ->assertSessionHasNoErrors();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->receipts()->count())->toBe(2);
});

test('a receipt cannot exceed the open amount or be dated in the future', function () {
    ['user' => $user, 'lease' => $lease] = paymentScenario();
    $payment = Payment::factory()->for($lease)->create();

    $this->actingAs($user)
        ->post(route('payments.receipts.store', $payment), receiptPayload(['amount' => '1000.01', 'date' => '2026-10-16']))
        ->assertSessionHasErrors(['amount', 'date']);

    expect($payment->receipts()->count())->toBe(0);
});

test('receipts cannot be registered for paid or canceled payments', function (PaymentStatus $status) {
    ['user' => $user, 'lease' => $lease] = paymentScenario();
    $payment = Payment::factory()->for($lease)->create(['status' => $status]);

    $this->actingAs($user)
        ->post(route('payments.receipts.store', $payment), receiptPayload(['amount' => '1.00']))
        ->assertForbidden();
})->with([PaymentStatus::Paid, PaymentStatus::Canceled]);

test('deleting a receipt recalculates the payment status', function () {
    ['user' => $user, 'lease' => $lease] = paymentScenario();
    $payment = Payment::factory()->for($lease)->create();
    $receipt = Receipt::factory()->for($payment)->create();
    $payment->refreshStatus();

    $this->actingAs($user)->delete(route('receipts.destroy', $receipt));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(Receipt::count())->toBe(0);
});

test('a user cannot manage payments from another account', function () {
    ['user' => $user] = paymentScenario();
    $otherPayment = Payment::factory()->create();
    $otherReceipt = Receipt::factory()->create();

    $this->actingAs($user)
        ->post(route('payments.receipts.store', $otherPayment), receiptPayload(['amount' => '1.00']))
        ->assertNotFound();
    $this->actingAs($user)->delete(route('receipts.destroy', $otherReceipt))->assertNotFound();

    expect(Receipt::count())->toBe(1);
});
