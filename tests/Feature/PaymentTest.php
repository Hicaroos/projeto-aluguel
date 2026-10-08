<?php

use App\Actions\Payments\CreateExtraCharge;
use App\Enums\LeaseStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
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

test('a receipt can be displayed as a printable document', function () {
    ['user' => $user, 'lease' => $lease] = paymentScenario();
    $receipt = Receipt::factory()->for(Payment::factory()->for($lease))->create(['amount' => 1550.2]);

    $this->actingAs($user)
        ->get(route('receipts.show', $receipt))
        ->assertInertia(fn (Assert $page) => $page
            ->component('receipts/Show')
            ->where('receipt.id', $receipt->id)
            ->where('receipt.payment.lease.tenant.name', $lease->tenant->name)
            ->where('receipt.payment.lease.property.owner.name', $lease->property->owner->name)
            ->where('amountInWords', 'mil quinhentos e cinquenta reais e vinte centavos')
        );
});

test('a receipt from another account cannot be displayed', function () {
    ['user' => $user] = paymentScenario();

    $this->actingAs($user)
        ->get(route('receipts.show', Receipt::factory()->create()))
        ->assertNotFound();
});

test('a receipt can be generated as a PDF to share, only for its own account', function () {
    ['user' => $user, 'lease' => $lease] = paymentScenario();
    $payment = Payment::factory()->for($lease)->create(['type' => PaymentType::Rent, 'reference_month' => '2026-10-01']);
    $receipt = Receipt::factory()->for($payment)->create(['amount' => 1550.2]);
    $lease->tenant->update(['name' => 'Ana Paula Ribeiro']);

    $this->actingAs($user)
        ->get(route('receipts.pdf', $receipt))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'inline; filename=recibo-outubro-de-2026-ana-paula-ribeiro.pdf');

    $this->actingAs($user)
        ->get(route('receipts.pdf', Receipt::factory()->create()))
        ->assertNotFound();
});

test('receipt amounts are written in words', function (float $amount, string $words) {
    expect((new Receipt(['amount' => $amount]))->amountInWords())->toBe($words);
})->with([
    [1.0, 'um real'],
    [0.01, 'um centavo'],
    [0.5, 'cinquenta centavos'],
    [2000.0, 'dois mil reais'],
    [1000000.0, 'um milhão de reais'],
    [2321.99, 'dois mil trezentos e vinte e um reais e noventa e nove centavos'],
]);

test('an extra charge can be created for a finished lease and is listed in its due month', function () {
    ['user' => $user, 'lease' => $lease] = paymentScenario();
    $lease->update(['status' => LeaseStatus::Ended]);

    $this->actingAs($user)
        ->post(route('payments.store'), [
            'lease_id' => $lease->id,
            'description' => 'Reparo da pintura',
            'amount' => '800.00',
            'due_date' => '2026-10-20',
        ])
        ->assertSessionHasNoErrors();

    $charge = Payment::extra()->first();

    expect($charge->lease_id)->toBe($lease->id)
        ->and($charge->description)->toBe('Reparo da pintura')
        ->and($charge->reference_month)->toBeNull()
        ->and($charge->status)->toBe(PaymentStatus::Pending);

    $this->actingAs($user)
        ->get(route('payments.index', ['lease' => $lease->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('payments.data', 1)
            ->where('payments.data.0.type', 'extra')
            ->where('payments.data.0.description', 'Reparo da pintura')
        );
});

test('an extra charge requires a description and a lease of the account', function () {
    ['user' => $user] = paymentScenario();
    $otherLease = Lease::factory()->create();

    $this->actingAs($user)
        ->post(route('payments.store'), [
            'lease_id' => $otherLease->id,
            'description' => '',
            'amount' => '800.00',
            'due_date' => '2026-10-20',
        ])
        ->assertSessionHasErrors(['lease_id', 'description']);

    expect(Payment::extra()->count())->toBe(0);
});

test('the receipt of an extra charge carries its description', function () {
    ['user' => $user, 'lease' => $lease] = paymentScenario();
    $charge = app(CreateExtraCharge::class)->handle($lease, ['description' => 'Multa rescisória', 'amount' => 500, 'due_date' => '2026-10-10']);
    $receipt = Receipt::factory()->for($charge)->create(['amount' => 500]);

    $this->actingAs($user)
        ->get(route('receipts.show', $receipt))
        ->assertInertia(fn (Assert $page) => $page
            ->where('receipt.payment.type', 'extra')
            ->where('receipt.payment.description', 'Multa rescisória')
        );
});

test('an extra charge without receipts can be deleted', function () {
    ['user' => $user, 'lease' => $lease] = paymentScenario();
    $charge = app(CreateExtraCharge::class)->handle($lease, ['description' => 'Reparo da pintura', 'amount' => 800, 'due_date' => '2026-10-20']);

    $this->actingAs($user)
        ->delete(route('payments.destroy', $charge))
        ->assertSessionHasNoErrors();

    expect(Payment::find($charge->id))->toBeNull();
});

test('rent payments and extra charges with receipts cannot be deleted', function () {
    ['user' => $user, 'lease' => $lease] = paymentScenario();
    $rent = Payment::factory()->for($lease)->create();
    $paidCharge = app(CreateExtraCharge::class)->handle($lease, ['description' => 'Multa', 'amount' => 300, 'due_date' => '2026-10-10']);
    Receipt::factory()->for($paidCharge)->create(['amount' => 100]);

    $this->actingAs($user)->delete(route('payments.destroy', $rent))->assertForbidden();
    $this->actingAs($user)->delete(route('payments.destroy', $paidCharge))->assertForbidden();

    expect(Payment::count())->toBe(2);
});

test('a user cannot delete an extra charge from another account', function () {
    ['user' => $user] = paymentScenario();
    $otherCharge = app(CreateExtraCharge::class)->handle(Lease::factory()->create(), ['description' => 'Multa', 'amount' => 300, 'due_date' => '2026-10-10']);

    $this->actingAs($user)->delete(route('payments.destroy', $otherCharge))->assertNotFound();

    expect(Payment::find($otherCharge->id))->not->toBeNull();
});

test('index sorts payments by due date by default and can sort by amount or filter by type', function () {
    ['user' => $user, 'lease' => $lease] = paymentScenario();
    $this->actingAs($user)->get(route('payments.index'));
    app(CreateExtraCharge::class)->handle($lease, ['description' => 'Multa', 'amount' => 5000, 'due_date' => '2026-10-02']);
    Lease::factory()->for($lease->account, 'account')->create(['start_date' => '2026-10-01', 'end_date' => '2027-09-30', 'due_day' => 20, 'amount' => 700]);
    $this->actingAs($user)->get(route('payments.index'));

    $this->actingAs($user)
        ->get(route('payments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'due_date')
            ->where('payments.data.0.due_date', '2026-10-02')
            ->where('payments.data.2.due_date', '2026-10-20')
        );

    $this->actingAs($user)
        ->get(route('payments.index', ['sort' => 'amount', 'direction' => 'desc']))
        ->assertInertia(fn (Assert $page) => $page->where('payments.data.0.amount', '5000.00'));

    $this->actingAs($user)
        ->get(route('payments.index', ['type' => 'extra']))
        ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1));
});
