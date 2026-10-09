<?php

use App\Enums\PaymentStatus;
use App\Enums\PropertyStatus;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-15 09:00:00');
});

/**
 * An agency with a paid rent in Ouricuri, an overdue one in Araripina and a vacant property.
 *
 * @return array{account: Account, admin: User, ouricuri: Branch, araripina: Branch}
 */
function managementScenario(): array
{
    $account = Account::factory()->agency()->create();
    $admin = User::factory()->for($account, 'account')->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $ouricuri = Branch::factory()->for($account, 'account')->create(['name' => 'Ouricuri']);
    $araripina = Branch::factory()->for($account, 'account')->create(['name' => 'Araripina']);

    $rentIn = function (Branch $branch, float $amount) use ($account, $owner): Payment {
        $lease = Lease::factory()->for($account, 'account')->create([
            'property_id' => Property::factory()->for($account, 'account')->for($owner, 'owner')->create([
                'branch_id' => $branch->id,
                'status' => PropertyStatus::Rented,
            ])->id,
            'tenant_id' => Tenant::factory()->for($account, 'account')->create()->id,
            'start_date' => '2026-01-01',
            'end_date' => '2028-12-31',
            'amount' => $amount,
        ]);

        return Payment::factory()->for($lease, 'lease')->create([
            'reference_month' => '2026-10-01',
            'due_date' => '2026-10-10',
            'amount' => $amount,
        ]);
    };

    $paid = $rentIn($ouricuri, 1000);
    Receipt::factory()->for($paid)->create(['amount' => 1000, 'date' => '2026-10-09']);
    $paid->update(['status' => PaymentStatus::Paid]);

    $rentIn($araripina, 800);

    Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['branch_id' => $araripina->id]);

    return ['account' => $account, 'admin' => $admin, 'ouricuri' => $ouricuri, 'araripina' => $araripina];
}

test('admins compare every branch side by side, whatever branch is selected', function () {
    ['admin' => $admin, 'ouricuri' => $ouricuri] = managementScenario();

    $this->actingAs($admin)
        ->withSession([User::SELECTED_BRANCH_SESSION_KEY => $ouricuri->id])
        ->get(route('management'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('management/Index')
            ->where('agency.expected', 1800)
            ->where('agency.received', 1000)
            ->where('agency.overdue', 800)
            ->where('agency.properties', 3)
            ->has('branches', 2)
            ->where('branches.0.name', 'Araripina')
            ->where('branches.0.summary.received', 0)
            ->where('branches.0.summary.overdue', 800)
            ->where('branches.0.summary.overdueCount', 1)
            ->where('branches.0.summary.vacantProperties', 1)
            ->where('branches.1.name', 'Ouricuri')
            ->where('branches.1.summary.received', 1000)
            ->where('branches.1.summary.activeLeases', 1)
            ->where('team.active', 1)
            ->where('team.recent.0.id', $admin->id)
            ->where('branchSelector.selectedId', $ouricuri->id)
        );
});

test('only agency administrators see the overview', function (Role $role) {
    ['account' => $account] = managementScenario();
    $member = User::factory()->for($account, 'account')->withRole($role)->create();

    $this->actingAs($member)->get(route('management'))->assertForbidden();
})->with([Role::General, Role::Agent, Role::Finance]);

test('single owners have no agency overview', function () {
    $user = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($user)->get(route('management'))->assertForbidden();
});
