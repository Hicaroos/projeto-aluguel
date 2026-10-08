<?php

use App\Actions\Leases\RenderLeaseRenewalAmendment;
use App\Enums\GuaranteeType;
use App\Enums\LeaseStatus;
use App\Models\Account;
use App\Models\Guarantor;
use App\Models\Lease;
use App\Models\LeaseRenewal;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{user: User, lease: Lease}
 */
function renewalScenario(array $leaseAttributes = []): array
{
    $account = Account::factory()->create();

    return [
        'user' => User::factory()->for($account, 'account')->create(),
        'lease' => Lease::factory()->for($account, 'account')->create([
            'start_date' => '2025-10-01',
            'end_date' => '2026-09-30',
            'amount' => 1500,
            'due_day' => 10,
            'status' => LeaseStatus::Active,
            'created_at' => '2025-10-01',
            ...$leaseAttributes,
        ]),
    ];
}

test('renewing extends the lease term, keeps the rent and records the renewal', function () {
    $this->travelTo('2026-09-01');
    ['user' => $user, 'lease' => $lease] = renewalScenario();

    $this->actingAs($user)
        ->post(route('leases.renewals.store', $lease), [
            'new_end_date' => '2027-09-30',
            'notes' => 'Renovado por mais um ano',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $lease->refresh();
    $renewal = $lease->renewals()->sole();

    expect($lease->end_date->toDateString())->toBe('2027-09-30')
        ->and($lease->amount)->toBe('1500.00')
        ->and($renewal->previous_end_date->toDateString())->toBe('2026-09-30')
        ->and($renewal->new_end_date->toDateString())->toBe('2027-09-30')
        ->and($renewal->notes)->toBe('Renovado por mais um ano');
});

test('renewing brings the next anniversary into the term, making the adjustment available', function () {
    $this->travelTo('2026-09-15');
    ['user' => $user, 'lease' => $lease] = renewalScenario();

    expect($lease->next_adjustment_date)->toBeNull();

    $this->actingAs($user)->post(route('leases.renewals.store', $lease), ['new_end_date' => '2027-09-30']);

    $lease = $lease->fresh();

    expect($lease->next_adjustment_date)->toBe('2026-10-01')
        ->and($lease->adjustment_status)->toBe('available');
});

test('renewing a lease that already ended generates the payments of the months it missed', function () {
    $this->travelTo('2026-11-15');
    ['user' => $user, 'lease' => $lease] = renewalScenario();

    $this->actingAs($user)->post(route('leases.renewals.store', $lease), ['new_end_date' => '2027-09-30']);

    $months = $lease->payments()->rent()->pluck('reference_month')->map->toDateString();

    expect($months)->toContain('2026-10-01', '2026-11-01', '2026-12-01')
        ->not->toContain('2027-01-01');
});

test('the new end date must come after the current one and within 10 years', function (string $newEndDate) {
    ['user' => $user, 'lease' => $lease] = renewalScenario();

    $this->actingAs($user)
        ->post(route('leases.renewals.store', $lease), ['new_end_date' => $newEndDate])
        ->assertSessionHasErrors('new_end_date');

    expect($lease->renewals()->count())->toBe(0);
})->with([
    'before the current end' => '2026-08-31',
    'the current end' => '2026-09-30',
    'more than 10 years' => '2036-10-01',
]);

test('only active leases of the same account can be renewed', function () {
    ['user' => $user, 'lease' => $endedLease] = renewalScenario(['status' => LeaseStatus::Ended]);

    $this->actingAs($user)
        ->post(route('leases.renewals.store', $endedLease), ['new_end_date' => '2027-09-30'])
        ->assertForbidden();

    ['lease' => $otherLease] = renewalScenario();

    $this->actingAs($user)
        ->post(route('leases.renewals.store', $otherLease), ['new_end_date' => '2027-09-30'])
        ->assertNotFound();
});

test('the amendment describes the parties, the previous and the new term', function () {
    ['lease' => $lease] = renewalScenario(['guarantee_type' => GuaranteeType::Guarantor]);
    Guarantor::factory()->for($lease)->create(['name' => 'Carlos Fiador']);
    $renewal = LeaseRenewal::factory()->for($lease)->create([
        'previous_end_date' => '2026-09-30',
        'new_end_date' => '2027-09-30',
        'created_at' => '2026-09-10',
    ]);

    $html = app(RenderLeaseRenewalAmendment::class)->handle($renewal);

    expect($html)
        ->toContain('TERMO ADITIVO DE PRORROGAÇÃO')
        ->toContain($lease->tenant->name)
        ->toContain('Carlos Fiador')
        ->toContain('terminaria em 30 de setembro de 2026')
        ->toContain('12 meses, passando a terminar em')
        ->toContain('30 de setembro de 2027')
        ->toContain('O FIADOR declara sua expressa anuência')
        ->toContain('10 de setembro de 2026');
});

test('the amendment is generated as a PDF only for renewals of the lease and of the same account', function () {
    ['user' => $user, 'lease' => $lease] = renewalScenario();
    $renewal = LeaseRenewal::factory()->for($lease)->create();
    ['lease' => $otherLease] = renewalScenario(['account_id' => $lease->account_id]);

    $this->actingAs($user)
        ->get(route('leases.renewals.amendment', [$lease, $renewal]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($user)
        ->get(route('leases.renewals.amendment', [$otherLease, $renewal]))
        ->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('leases.renewals.amendment', [$lease, $renewal]))
        ->assertNotFound();
});

test('the leases list sends each lease renewals', function () {
    ['user' => $user, 'lease' => $lease] = renewalScenario();
    LeaseRenewal::factory()->for($lease)->create(['new_end_date' => '2027-09-30']);

    $this->actingAs($user)
        ->get(route('leases.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('leases.data.0.renewals', 1)
            ->where('leases.data.0.renewals.0.new_end_date', '2027-09-30')
        );
});
