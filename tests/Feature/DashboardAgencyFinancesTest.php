<?php

use App\Enums\ExpensePayer;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\OwnerLedgerEntry;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
});

/**
 * An agency's October: fees, a sale, late and extra charges partly kept, an expense it bore,
 * a payout, and rent overdue for a few days and for months.
 *
 * @return array{account: Account, user: User, owner: Owner, house: Property}
 */
function agencyFinancesScenario(): array
{
    $account = Account::factory()->agency()->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $house = Property::factory()->for($account, 'account')->for($owner, 'owner')->create();
    $lease = Lease::factory()->for($account, 'account')->for($house)->for(Tenant::factory()->for($account, 'account'))->create(['amount' => 2000, 'start_date' => '2026-01-10', 'end_date' => '2028-07-09']);

    $entry = fn (LedgerEntryType $type, float $amount, string $availableOn = '2026-10-05') => OwnerLedgerEntry::factory()->for($owner)->create([
        'property_id' => $house->id,
        'type' => $type,
        'amount' => $amount,
        'occurred_on' => '2026-10-05',
        'available_on' => $availableOn,
    ]);

    $entry(LedgerEntryType::Rent, 3000);
    $entry(LedgerEntryType::AdminFee, -200);
    $entry(LedgerEntryType::IntermediationFee, -1000);
    $entry(LedgerEntryType::RenewalFee, -500);
    $entry(LedgerEntryType::LateCharges, 30);
    $entry(LedgerEntryType::ExtraCharge, 100);
    $entry(LedgerEntryType::Payout, -300);
    $entry(LedgerEntryType::Rent, 1000, '2026-10-20');

    $rent = Payment::factory()->for($lease)->create(['amount' => 2000, 'due_date' => '2026-10-10', 'status' => PaymentStatus::Paid]);
    Receipt::factory()->for($rent)->create(['amount' => 2000, 'late_fee_amount' => 40, 'interest_amount' => 10, 'date' => '2026-10-12']);
    $extra = Payment::factory()->for($lease)->create(['type' => PaymentType::Extra, 'reference_month' => null, 'amount' => 150, 'due_date' => '2026-10-12', 'status' => PaymentStatus::Paid]);
    Receipt::factory()->for($extra)->create(['amount' => 150, 'date' => '2026-10-12']);
    Payment::factory()->for($lease)->create(['amount' => 1200, 'due_date' => '2026-10-05', 'reference_month' => '2026-09-01']);
    Payment::factory()->for($lease)->create(['amount' => 900, 'due_date' => '2026-08-01', 'reference_month' => '2026-08-01']);

    Sale::factory()->for($account, 'account')->create(['commission_amount' => 6000, 'sold_on' => '2026-10-02']);
    Expense::factory()->paid()->for($account, 'account')->for($house)->create(['charged_to' => ExpensePayer::Agency, 'amount' => 80, 'due_date' => '2026-10-08', 'payment_date' => '2026-10-08']);
    Expense::factory()->paid()->for($account, 'account')->for($house)->create(['charged_to' => ExpensePayer::Owner, 'amount' => 300, 'due_date' => '2026-10-08', 'payment_date' => '2026-10-08']);

    return [
        'account' => $account,
        'user' => User::factory()->for($account, 'account')->create(),
        'owner' => $owner,
        'house' => $house,
    ];
}

test('the dashboard shows what the agency earned itself in the month', function () {
    ['user' => $user] = agencyFinancesScenario();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('agencyFinances.revenue.adminFees', 200)
            ->where('agencyFinances.revenue.intermediationFees', 1000)
            ->where('agencyFinances.revenue.renewalFees', 500)
            ->where('agencyFinances.revenue.saleCommissions', 6000)
            ->where('agencyFinances.revenue.lateCharges', 20)
            ->where('agencyFinances.revenue.extraCharges', 50)
            ->where('agencyFinances.revenue.expenses', 80)
            ->where('agencyFinances.revenue.result', 7690)
        );
});

test('the dashboard shows the owners money the agency holds, the expenses they bore included', function () {
    ['user' => $user] = agencyFinancesScenario();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('agencyFinances.owners.received', 2200)
            ->where('agencyFinances.owners.available', 830)
            ->where('agencyFinances.owners.pending', 1000)
            ->where('agencyFinances.owners.paidOut', 300)
        );
});

test('the dashboard sums up the overdue rent by how late it is', function () {
    ['user' => $user] = agencyFinancesScenario();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('agencyFinances.delinquency.total', 2100)
            ->where('agencyFinances.delinquency.count', 2)
            ->where('agencyFinances.delinquency.upTo30', 1200)
            ->where('agencyFinances.delinquency.upTo60', 0)
            ->where('agencyFinances.delinquency.over60', 900)
            ->where('agencyFinances.delinquency.rate', 35.8)
        );
});

test('only agency members who handle the money see the agency finances', function () {
    ['account' => $account] = agencyFinancesScenario();
    $agent = User::factory()->for($account, 'account')->withRole(Role::Agent)->create();
    $owner = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($agent)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('agencyFinances', null));
    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('agencyFinances', null));
});

test('with an owner picked, the agency finances count only what concerns them', function () {
    ['account' => $account, 'user' => $user, 'owner' => $owner] = agencyFinancesScenario();
    $other = Owner::factory()->for($account, 'account')->create();
    $otherHouse = Property::factory()->for($account, 'account')->for($other, 'owner')->create();
    OwnerLedgerEntry::factory()->for($other)->create(['property_id' => $otherHouse->id, 'type' => LedgerEntryType::AdminFee, 'amount' => -999, 'occurred_on' => '2026-10-05']);

    $this->actingAs($user)
        ->withSession([User::SELECTED_OWNER_SESSION_KEY => $owner->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('agencyFinances.revenue.adminFees', 200)
            ->where('agencyFinances.revenue.saleCommissions', 0)
        );
});
