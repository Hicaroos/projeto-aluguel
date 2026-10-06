<?php

use App\Actions\Payments\CreateExtraCharge;
use App\Enums\PropertyStatus;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard summarizes the account figures', function () {
    $this->travelTo('2026-10-15 09:00:00');

    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();
    $rentedProperty = Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['status' => PropertyStatus::Rented]);
    Property::factory()->for($account, 'account')->for($owner, 'owner')->count(2)->create(['status' => PropertyStatus::Available]);
    $lease = Lease::factory()->for($account, 'account')->for($rentedProperty, 'property')->create([
        'start_date' => '2025-12-01',
        'end_date' => '2026-11-30',
        'due_day' => 10,
        'amount' => 1000,
    ]);

    $september = Payment::factory()->for($lease)->create(['reference_month' => '2026-09-01', 'due_date' => '2026-09-10']);
    $october = Payment::factory()->for($lease)->create(['reference_month' => '2026-10-01', 'due_date' => '2026-10-10']);
    Receipt::factory()->for($september)->create(['amount' => 1000]);
    $september->refreshStatus();
    Receipt::factory()->for($october)->create(['amount' => 300]);
    $october->refreshStatus();

    Payment::factory()->create(['due_date' => '2026-10-05']);
    Expense::factory()->paid()->for($account, 'account')->for($rentedProperty)->create(['due_date' => '2026-10-05', 'amount' => 200]);
    Expense::factory()->for($account, 'account')->for($rentedProperty)->create(['due_date' => '2026-10-25', 'amount' => 50]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('month', '2026-10-01')
            ->where('stats.expected', 1000)
            ->where('stats.received', 300)
            ->where('stats.overdue', 700)
            ->where('stats.overdueCount', 1)
            ->where('stats.expensesPaid', 200)
            ->where('stats.expensesPending', 50)
            ->where('stats.netIncome', 100)
            ->where('stats.properties', 3)
            ->where('stats.rentedProperties', 1)
            ->where('stats.activeLeases', 1)
            ->has('monthlyRevenue', 6)
            ->where('monthlyRevenue.4.month', '2026-09-01')
            ->where('monthlyRevenue.4.received', 1000)
            ->where('monthlyRevenue.5.expected', 1000)
            ->has('attentionPayments', 1)
            ->where('attentionPayments.0.id', $october->id)
            ->has('endingLeases', 1)
            ->has('vacantProperties', 2)
            ->where('vacantPropertiesCount', 2)
        );
});

test('the dashboard lists overdue debts left by former tenants separately', function () {
    $this->travelTo('2026-10-15 09:00:00');

    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();
    $activeLease = Lease::factory()->for($account, 'account')->create(['start_date' => '2026-01-01', 'end_date' => '2028-06-30']);
    $finishedLease = Lease::factory()->ended()->for($account, 'account')->create();

    $activeDebt = app(CreateExtraCharge::class)->handle($activeLease, ['description' => 'Conta de água', 'amount' => 90, 'due_date' => '2026-10-05']);
    $formerDebt = app(CreateExtraCharge::class)->handle($finishedLease, ['description' => 'Reparo da pintura', 'amount' => 800, 'due_date' => '2026-09-20']);
    app(CreateExtraCharge::class)->handle($finishedLease, ['description' => 'Multa', 'amount' => 300, 'due_date' => '2026-11-20']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('formerTenantDebts', 1)
            ->where('formerTenantDebts.0.id', $formerDebt->id)
            ->where('attentionPayments', fn ($payments) => collect($payments)->pluck('id')->contains($activeDebt->id)
                && ! collect($payments)->pluck('id')->contains($formerDebt->id))
        );
});
