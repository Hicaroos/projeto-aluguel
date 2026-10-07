<?php

use App\Actions\Leases\SyncLeasePayments;
use App\Actions\Payments\CreateExtraCharge;
use App\Enums\PaymentStatus;
use App\Enums\PropertyStatus;
use App\Models\Account;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
});

/**
 * @return array<string, string>
 */
function scheduleOf(Lease $lease): array
{
    return $lease->payments()
        ->orderBy('reference_month')
        ->get()
        ->mapWithKeys(fn (Payment $payment) => [$payment->reference_month->toDateString() => $payment->due_date->toDateString()])
        ->all();
}

test('it generates the payments of the current and the next month', function () {
    $lease = Lease::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2028-06-30', 'due_day' => 10, 'amount' => 1500]);

    app(SyncLeasePayments::class)->handle($lease);

    expect(scheduleOf($lease))->toBe([
        '2026-10-01' => '2026-10-10',
        '2026-11-01' => '2026-11-10',
    ])->and($lease->payments()->first()->amount)->toBe('1500.00');
});

test('it is safe to run many times', function () {
    $lease = Lease::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2028-06-30']);

    app(SyncLeasePayments::class)->handle($lease);
    app(SyncLeasePayments::class)->handle($lease);

    expect($lease->payments()->count())->toBe(2);
});

test('the first payment is due on the lease start when the due day comes before it', function () {
    $lease = Lease::factory()->create(['start_date' => '2026-10-12', 'end_date' => '2027-10-11', 'due_day' => 10]);

    app(SyncLeasePayments::class)->handle($lease);

    expect(scheduleOf($lease))->toBe([
        '2026-10-01' => '2026-10-12',
        '2026-11-01' => '2026-11-10',
    ]);
});

test('changing the due day keeps each payment in its own month', function () {
    $lease = Lease::factory()->create(['start_date' => '2026-07-15', 'end_date' => '2027-07-14', 'due_day' => 20]);
    $lease->forceFill(['created_at' => '2026-10-01'])->saveQuietly();
    app(SyncLeasePayments::class)->handle($lease);

    expect(scheduleOf($lease))->toBe([
        '2026-10-01' => '2026-10-20',
        '2026-11-01' => '2026-11-20',
    ]);

    $lease->update(['due_day' => 5]);
    app(SyncLeasePayments::class)->handle($lease);

    expect(scheduleOf($lease))->toBe([
        '2026-10-01' => '2026-10-05',
        '2026-11-01' => '2026-11-05',
    ]);
});

test('the due day is capped to the last day of short months', function () {
    $lease = Lease::factory()->create(['start_date' => '2026-10-01', 'end_date' => '2027-09-30', 'due_day' => 31]);

    app(SyncLeasePayments::class)->handle($lease);

    expect(scheduleOf($lease))->toBe([
        '2026-10-01' => '2026-10-31',
        '2026-11-01' => '2026-11-30',
    ]);
});

test('it does not generate payments after the lease end', function () {
    $lease = Lease::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-10-31', 'due_day' => 10]);

    app(SyncLeasePayments::class)->handle($lease);

    expect(scheduleOf($lease))->toBe(['2026-10-01' => '2026-10-10']);
});

test('it does not generate payments for finished leases', function () {
    $lease = Lease::factory()->ended()->create(['start_date' => '2026-01-01', 'end_date' => '2028-06-30']);

    app(SyncLeasePayments::class)->handle($lease);

    expect($lease->payments()->count())->toBe(0);
});

test('it updates only the upcoming pending payments when the lease terms change', function () {
    $lease = Lease::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2028-06-30', 'due_day' => 10, 'amount' => 1500]);
    app(SyncLeasePayments::class)->handle($lease);

    $lease->update(['amount' => 1800, 'due_day' => 20]);
    app(SyncLeasePayments::class)->handle($lease);

    $october = $lease->payments()->whereDate('reference_month', '2026-10-01')->first();
    $november = $lease->payments()->whereDate('reference_month', '2026-11-01')->first();

    expect($october->amount)->toBe('1500.00')
        ->and($october->due_date->toDateString())->toBe('2026-10-10')
        ->and($november->amount)->toBe('1800.00')
        ->and($november->due_date->toDateString())->toBe('2026-11-20');
});

test('it cancels pending payments that no longer fit the lease period', function () {
    $lease = Lease::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2028-06-30']);
    app(SyncLeasePayments::class)->handle($lease);

    $lease->update(['end_date' => '2026-10-31']);
    app(SyncLeasePayments::class)->handle($lease);

    expect($lease->payments()->whereDate('reference_month', '2026-11-01')->first()->status)->toBe(PaymentStatus::Canceled)
        ->and($lease->payments()->whereDate('reference_month', '2026-10-01')->first()->status)->toBe(PaymentStatus::Pending);
});

test('cancelling upcoming payments keeps the ones already due', function () {
    $lease = Lease::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2028-06-30', 'due_day' => 10]);
    app(SyncLeasePayments::class)->handle($lease);

    app(SyncLeasePayments::class)->cancelUpcoming($lease);

    expect($lease->payments()->whereDate('reference_month', '2026-10-01')->first()->status)->toBe(PaymentStatus::Pending)
        ->and($lease->payments()->whereDate('reference_month', '2026-11-01')->first()->status)->toBe(PaymentStatus::Canceled);
});

test('the scheduled command generates payments for every active lease', function () {
    $activeLease = Lease::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2028-06-30']);
    $endedLease = Lease::factory()->ended()->create(['start_date' => '2026-01-01', 'end_date' => '2028-06-30']);

    $this->artisan('app:generate-lease-payments')->assertSuccessful();

    expect($activeLease->payments()->count())->toBe(2)
        ->and($endedLease->payments()->count())->toBe(0);
});

test('creating and finishing a lease generates and cancels its payments', function () {
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();
    $property = Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['status' => PropertyStatus::Available]);
    $tenant = Tenant::factory()->for($account, 'account')->create();

    $this->actingAs($user)->post(route('leases.store'), [
        'property_id' => $property->id,
        'tenant_id' => $tenant->id,
        'start_date' => '2026-10-01',
        'end_date' => '2027-09-30',
        'amount' => '2000.00',
        'due_day' => 20,
        'guarantee_type' => 'none',
    ])->assertSessionHasNoErrors();

    $lease = Lease::first();

    expect(scheduleOf($lease))->toBe([
        '2026-10-01' => '2026-10-20',
        '2026-11-01' => '2026-11-20',
    ]);

    $this->actingAs($user)->patch(route('leases.finish', $lease), ['status' => 'terminated']);

    expect($lease->payments()->where('status', PaymentStatus::Canceled)->count())->toBe(2);
});

test('extra charges are left untouched by the rent sync and kept when the lease is finished', function () {
    $lease = Lease::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2028-06-30', 'due_day' => 10, 'amount' => 1500]);
    app(SyncLeasePayments::class)->handle($lease);

    $repair = app(CreateExtraCharge::class)->handle($lease, [
        'description' => 'Reparo da pintura',
        'amount' => 800,
        'due_date' => '2026-10-25',
    ]);

    $lease->update(['amount' => 1800]);
    app(SyncLeasePayments::class)->handle($lease);
    app(SyncLeasePayments::class)->cancelUpcoming($lease);

    expect($lease->payments()->rent()->count())->toBe(2)
        ->and($repair->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($repair->fresh()->amount)->toBe('800.00')
        ->and($repair->fresh()->reference_month)->toBeNull();
});
