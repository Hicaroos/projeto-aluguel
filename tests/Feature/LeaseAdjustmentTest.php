<?php

use App\Actions\ContractTemplates\RenderLeaseContract;
use App\Enums\AdjustmentIndex;
use App\Enums\PaymentStatus;
use App\Models\Account;
use App\Models\Lease;
use App\Models\LeaseAdjustment;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{user: User, lease: Lease}
 */
function adjustmentScenario(array $leaseAttributes = []): array
{
    $account = Account::factory()->create();

    return [
        'user' => User::factory()->for($account, 'account')->create(),
        'lease' => Lease::factory()->for($account, 'account')->create([
            'start_date' => '2026-01-01',
            'end_date' => '2028-12-31',
            'amount' => 2000,
            'due_day' => 10,
            'adjustment_index' => AdjustmentIndex::Ipca,
            ...$leaseAttributes,
        ]),
    ];
}

test('the next adjustment is one year after the start for every adjustment applied', function () {
    $this->travelTo('2026-06-01');
    ['lease' => $lease] = adjustmentScenario();

    expect($lease->next_adjustment_date)->toBe('2027-01-01');

    LeaseAdjustment::factory()->for($lease)->create(['effective_on' => '2027-01-01']);

    expect($lease->fresh()->next_adjustment_date)->toBe('2028-01-01');

    $shortLease = adjustmentScenario(['end_date' => '2026-12-31'])['lease'];

    expect($shortLease->next_adjustment_date)->toBeNull();
});

test('the adjustment becomes available 30 days before the anniversary and overdue after it', function (string $today, ?string $status) {
    $this->travelTo($today);
    ['lease' => $lease] = adjustmentScenario();

    expect($lease->adjustment_status)->toBe($status);
})->with([
    'mais de 30 dias antes' => ['2026-12-01', null],
    'a 30 dias' => ['2026-12-02', 'available'],
    'no aniversário' => ['2027-01-01', 'available'],
    'depois do aniversário' => ['2027-01-02', 'overdue'],
]);

test('applying an adjustment updates the rent, the pending payments from the anniversary on and the history', function () {
    $this->travelTo('2027-02-15');
    ['user' => $user, 'lease' => $lease] = adjustmentScenario();

    $december = Payment::factory()->for($lease)->create(['reference_month' => '2026-12-01', 'due_date' => '2026-12-10', 'amount' => 2000]);
    $january = Payment::factory()->for($lease)->create(['reference_month' => '2027-01-01', 'due_date' => '2027-01-10', 'amount' => 2000]);
    $february = Payment::factory()->for($lease)->create(['reference_month' => '2027-02-01', 'due_date' => '2027-02-10', 'amount' => 2000]);
    $march = Payment::factory()->for($lease)->create(['reference_month' => '2027-03-01', 'due_date' => '2027-03-10', 'amount' => 2000]);
    Receipt::factory()->for($february)->create(['amount' => 500]);
    $february->refreshStatus();

    $this->actingAs($user)
        ->post(route('leases.adjustments.store', $lease), ['mode' => 'percent', 'percent' => '4.5', 'notes' => 'IPCA acumulado'])
        ->assertSessionHasNoErrors();

    $adjustment = $lease->adjustments()->sole();

    expect($lease->fresh()->amount)->toBe('2090.00')
        ->and($adjustment->effective_on->toDateString())->toBe('2027-01-01')
        ->and($adjustment->adjustment_index)->toBe(AdjustmentIndex::Ipca)
        ->and($adjustment->percent)->toBe('4.50')
        ->and($adjustment->previous_amount)->toBe('2000.00')
        ->and($adjustment->new_amount)->toBe('2090.00')
        ->and($adjustment->notes)->toBe('IPCA acumulado')
        ->and($december->fresh()->amount)->toBe('2000.00')
        ->and($january->fresh()->amount)->toBe('2090.00')
        ->and($february->fresh()->amount)->toBe('2000.00')
        ->and($february->fresh()->status)->toBe(PaymentStatus::Partial)
        ->and($march->fresh()->amount)->toBe('2090.00')
        ->and($lease->fresh()->next_adjustment_date)->toBe('2028-01-01');
});

test('the rent can be adjusted to a new agreed amount, recording the resulting percent', function () {
    $this->travelTo('2027-01-05');
    ['user' => $user, 'lease' => $lease] = adjustmentScenario(['adjustment_index' => AdjustmentIndex::Negotiated]);

    $this->actingAs($user)
        ->post(route('leases.adjustments.store', $lease), ['mode' => 'amount', 'new_amount' => '2100.00', 'notes' => 'Acordo com o inquilino'])
        ->assertSessionHasNoErrors();

    $adjustment = $lease->adjustments()->sole();

    expect($lease->fresh()->amount)->toBe('2100.00')
        ->and($adjustment->percent)->toBe('5.00')
        ->and($adjustment->adjustment_index)->toBe(AdjustmentIndex::Negotiated)
        ->and($adjustment->new_amount)->toBe('2100.00');
});

test('a new agreed amount must stay between half and double the current rent', function (string $newAmount) {
    $this->travelTo('2027-01-05');
    ['user' => $user, 'lease' => $lease] = adjustmentScenario();

    $this->actingAs($user)
        ->post(route('leases.adjustments.store', $lease), ['mode' => 'amount', 'new_amount' => $newAmount])
        ->assertSessionHasErrors('new_amount');

    expect($lease->adjustments()->count())->toBe(0);
})->with(['999.99', '4000.01', '0']);

test('the contract adjustment clause follows the index or the free negotiation', function () {
    ['lease' => $lease] = adjustmentScenario();
    $render = fn (Lease $lease): string => app(RenderLeaseContract::class)->render('<p><span data-variable="lease.adjustment_clause"></span></p>', $lease->fresh());

    expect($render($lease))->toContain('pela variação acumulada do índice IPCA/IBGE');

    $lease->update(['adjustment_index' => AdjustmentIndex::Negotiated]);

    expect($render($lease))->toContain('em valor livremente negociado entre as partes');
});

test('overdue anniversaries are adjusted one at a time', function () {
    $this->travelTo('2028-02-01');
    ['user' => $user, 'lease' => $lease] = adjustmentScenario();

    $this->actingAs($user)->post(route('leases.adjustments.store', $lease), ['mode' => 'percent', 'percent' => '10']);
    $this->actingAs($user)->post(route('leases.adjustments.store', $lease), ['mode' => 'percent', 'percent' => '-5']);

    expect($lease->adjustments()->pluck('effective_on')->map->toDateString()->all())->toBe(['2027-01-01', '2028-01-01'])
        ->and($lease->fresh()->amount)->toBe('2090.00');
});

test('an adjustment is refused before it is available, for finished leases and out of range percents', function () {
    $this->travelTo('2026-06-01');
    ['user' => $user, 'lease' => $lease] = adjustmentScenario();

    $this->actingAs($user)
        ->post(route('leases.adjustments.store', $lease), ['mode' => 'percent', 'percent' => '4'])
        ->assertForbidden();

    $this->travelTo('2027-01-05');

    $this->actingAs($user)
        ->post(route('leases.adjustments.store', $lease), ['mode' => 'percent', 'percent' => '150'])
        ->assertSessionHasErrors('percent');

    $lease->update(['status' => 'ended']);

    $this->actingAs($user)
        ->post(route('leases.adjustments.store', $lease), ['mode' => 'percent', 'percent' => '4'])
        ->assertForbidden();

    $otherLease = Lease::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2028-12-31']);

    $this->actingAs($user)
        ->post(route('leases.adjustments.store', $otherLease), ['mode' => 'percent', 'percent' => '4'])
        ->assertNotFound();

    expect(LeaseAdjustment::count())->toBe(0);
});

test('leases and dashboard show the adjustment status', function () {
    $this->travelTo('2027-01-05');
    ['user' => $user, 'lease' => $lease] = adjustmentScenario();
    Lease::factory()->for($lease->account, 'account')->create(['start_date' => '2026-06-01', 'end_date' => '2028-05-31']);

    $this->actingAs($user)
        ->get(route('leases.index', ['show' => $lease->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.adjustment_status', 'overdue')
            ->where('selected.next_adjustment_date', '2027-01-01')
            ->has('selected.adjustments', 0)
        );

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('adjustmentLeases', 1)
            ->where('adjustmentLeases.0.id', $lease->id)
            ->where('adjustmentLeases.0.adjustment_status', 'overdue')
        );
});
