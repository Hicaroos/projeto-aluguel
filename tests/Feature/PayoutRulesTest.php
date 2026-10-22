<?php

use App\Actions\Payouts\ResolvePayoutRules;
use App\Enums\GuaranteeType;
use App\Enums\PayoutMode;
use App\Enums\PayoutSchedule;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An agency with an owner, a property and a tenant, and an admin signed in.
 *
 * @return array{account: Account, admin: User, branch: Branch, owner: Owner, property: Property, tenant: Tenant}
 */
function payoutRulesScenario(): array
{
    $account = Account::factory()->agency()->create();
    $branch = Branch::factory()->for($account, 'account')->create();
    $owner = Owner::factory()->for($account, 'account')->create();

    return [
        'account' => $account,
        'admin' => User::factory()->for($account, 'account')->create(),
        'branch' => $branch,
        'owner' => $owner,
        'property' => Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['branch_id' => $branch->id]),
        'tenant' => Tenant::factory()->for($account, 'account')->create(),
    ];
}

/**
 * @return array<string, mixed>
 */
function payoutDefaultsPayload(array $overrides = []): array
{
    return [
        'admin_fee_percent' => '8',
        'intermediation_fee_percent' => '50',
        'renewal_fee_percent' => '50',
        'admin_fee_with_intermediation' => '0',
        'admin_fee_on_extra_charges' => '1',
        'extra_charges_to_owner' => '1',
        'late_charges_owner_percent' => '50',
        'payout_mode' => 'guaranteed',
        'payout_schedule' => 'monthly_day',
        'payout_days' => '3',
        'payout_day' => '15',
        ...$overrides,
    ];
}

test('agencies start with market defaults for fees and payouts', function () {
    $account = Account::factory()->agency()->create()->refresh();

    expect((new ResolvePayoutRules)->handle($account))->toBe([
        'admin_fee_percent' => 10.0,
        'intermediation_fee_percent' => 100.0,
        'renewal_fee_percent' => 0.0,
        'admin_fee_with_intermediation' => false,
        'admin_fee_on_extra_charges' => false,
        'extra_charges_to_owner' => true,
        'late_charges_owner_percent' => 100.0,
        'payout_mode' => PayoutMode::Received,
        'payout_schedule' => PayoutSchedule::DaysAfter,
        'payout_days' => 5,
        'payout_day' => 10,
    ]);
});

test('agency admins set the default fees and payout terms', function () {
    ['account' => $account, 'admin' => $admin] = payoutRulesScenario();

    $this->actingAs($admin)
        ->get(route('payout-settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Payouts')
            ->where('rules.admin_fee_percent', '10.00')
            ->where('rules.payout_mode', 'received')
        );

    $this->actingAs($admin)
        ->patch(route('payout-settings.update'), payoutDefaultsPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('payout-settings.edit'));

    $rules = (new ResolvePayoutRules)->handle($account->refresh());

    expect($rules['admin_fee_percent'])->toBe(8.0)
        ->and($rules['renewal_fee_percent'])->toBe(50.0)
        ->and($rules['admin_fee_on_extra_charges'])->toBeTrue()
        ->and($rules['late_charges_owner_percent'])->toBe(50.0)
        ->and($rules['payout_mode'])->toBe(PayoutMode::Guaranteed)
        ->and($rules['payout_schedule'])->toBe(PayoutSchedule::MonthlyDay)
        ->and($rules['payout_day'])->toBe(15);
});

test('every default needs a valid value', function () {
    ['admin' => $admin] = payoutRulesScenario();

    $this->actingAs($admin)
        ->patch(route('payout-settings.update'), payoutDefaultsPayload([
            'admin_fee_percent' => '',
            'intermediation_fee_percent' => '120',
            'payout_mode' => 'whenever',
            'payout_day' => '32',
        ]))
        ->assertSessionHasErrors(['admin_fee_percent', 'intermediation_fee_percent', 'payout_mode', 'payout_day']);
});

test('only agency admins reach the payout settings', function () {
    ['account' => $account] = payoutRulesScenario();
    $general = User::factory()->for($account, 'account')->withRole(Role::General)->create();
    $singleOwner = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($general)->get(route('payout-settings.edit'))->assertForbidden();
    $this->actingAs($general)->patch(route('payout-settings.update'), payoutDefaultsPayload())->assertForbidden();
    $this->actingAs($singleOwner)->get(route('payout-settings.edit'))->assertForbidden();
});

test('owners may set the rules otherwise, a blank value following the agency', function () {
    ['admin' => $admin, 'owner' => $owner] = payoutRulesScenario();

    $this->actingAs($admin)
        ->put(route('owners.update', $owner), [
            'name' => $owner->name,
            'cpf_cnpj' => $owner->cpf_cnpj,
            'phone' => '(87) 99999-0000',
            'admin_fee_percent' => '6',
            'payout_mode' => 'guaranteed',
            'intermediation_fee_percent' => '',
        ])
        ->assertSessionHasNoErrors();

    $owner->refresh();

    expect($owner->admin_fee_percent)->toBe('6.00')
        ->and($owner->payout_mode)->toBe(PayoutMode::Guaranteed)
        ->and($owner->intermediation_fee_percent)->toBeNull();
});

test('leases may set the rules otherwise again', function () {
    ['admin' => $admin, 'property' => $property, 'tenant' => $tenant] = payoutRulesScenario();

    $this->actingAs($admin)
        ->post(route('leases.store'), [
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2027-12-31',
            'amount' => '2000',
            'due_day' => 10,
            'guarantee_type' => GuaranteeType::None->value,
            'admin_fee_percent' => '12',
            'late_charges_owner_percent' => '0',
            'payout_schedule' => 'monthly_day',
            'payout_day' => '20',
        ])
        ->assertSessionHasNoErrors();

    $lease = Lease::where('property_id', $property->id)->sole();

    expect($lease->admin_fee_percent)->toBe('12.00')
        ->and($lease->late_charges_owner_percent)->toBe('0.00')
        ->and($lease->payout_schedule)->toBe(PayoutSchedule::MonthlyDay)
        ->and($lease->payout_day)->toBe(20)
        ->and($lease->payout_mode)->toBeNull();
});

test('the lease rules come first, then the owner rules, then the agency defaults', function () {
    ['account' => $account, 'owner' => $owner, 'property' => $property, 'tenant' => $tenant] = payoutRulesScenario();
    $account->update(['admin_fee_percent' => 10, 'intermediation_fee_percent' => 100, 'payout_days' => 5]);
    $owner->update(['admin_fee_percent' => 8, 'intermediation_fee_percent' => 0]);
    $lease = Lease::factory()->for($account, 'account')->for($property, 'property')->for($tenant, 'tenant')->create([
        'admin_fee_percent' => 7,
        'admin_fee_with_intermediation' => true,
    ]);

    $rules = (new ResolvePayoutRules)->handle($account->refresh(), $owner->refresh(), $lease->refresh());

    expect($rules['admin_fee_percent'])->toBe(7.0)
        ->and($rules['intermediation_fee_percent'])->toBe(0.0)
        ->and($rules['admin_fee_with_intermediation'])->toBeTrue()
        ->and($rules['payout_days'])->toBe(5);
});

test('payouts are due some days later or on the next fixed day of the month', function () {
    $receivedOn = CarbonImmutable::parse('2026-10-12');

    expect(PayoutSchedule::DaysAfter->payoutDate($receivedOn, 5, 10)->toDateString())->toBe('2026-10-17')
        ->and(PayoutSchedule::DaysAfter->payoutDate($receivedOn, 0, 10)->toDateString())->toBe('2026-10-12')
        ->and(PayoutSchedule::MonthlyDay->payoutDate($receivedOn, 5, 15)->toDateString())->toBe('2026-10-15')
        ->and(PayoutSchedule::MonthlyDay->payoutDate($receivedOn, 5, 12)->toDateString())->toBe('2026-10-12')
        ->and(PayoutSchedule::MonthlyDay->payoutDate($receivedOn, 5, 10)->toDateString())->toBe('2026-11-10')
        ->and(PayoutSchedule::MonthlyDay->payoutDate(CarbonImmutable::parse('2027-01-31'), 5, 31)->toDateString())->toBe('2027-01-31')
        ->and(PayoutSchedule::MonthlyDay->payoutDate(CarbonImmutable::parse('2027-02-01'), 5, 31)->toDateString())->toBe('2027-02-28');
});
