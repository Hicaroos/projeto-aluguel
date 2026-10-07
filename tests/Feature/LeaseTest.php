<?php

use App\Enums\AdjustmentIndex;
use App\Enums\GuaranteeType;
use App\Enums\LeasePurpose;
use App\Enums\LeaseStatus;
use App\Enums\MaritalStatus;
use App\Enums\PaymentStatus;
use App\Enums\PropertyStatus;
use App\Models\Account;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{account: Account, user: User, property: Property, tenant: Tenant}
 */
function leaseScenario(): array
{
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create();

    return [
        'account' => $account,
        'user' => User::factory()->for($account, 'account')->create(),
        'property' => Property::factory()->for($account, 'account')->for($owner, 'owner')->create(),
        'tenant' => Tenant::factory()->for($account, 'account')->create(),
    ];
}

function validLeasePayload(Property $property, Tenant $tenant, array $overrides = []): array
{
    return array_merge([
        'property_id' => $property->id,
        'tenant_id' => $tenant->id,
        'start_date' => '2026-01-01',
        'end_date' => '2028-06-30',
        'amount' => '1800.00',
        'due_day' => 10,
        'guarantee_type' => GuaranteeType::None->value,
        'notes' => 'Pagamento via Pix.',
    ], $overrides);
}

test('index only lists leases belonging to the authenticated account', function () {
    ['account' => $account, 'user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();
    Lease::factory()->for($account, 'account')->for($property, 'property')->for($tenant, 'tenant')->create();

    Lease::factory()->create();

    $this->actingAs($user)
        ->get(route('leases.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('leases/Index')
            ->has('leases.data', 1)
            ->where('leases.data.0.tenant.name', $tenant->name)
            ->has('properties', 1)
            ->has('tenants', 1)
        );
});

test('index filters leases by status and tenant name', function () {
    ['account' => $account, 'user' => $user, 'property' => $property] = leaseScenario();
    $property->update(['street' => 'Rua das Flores', 'neighborhood' => 'Centro', 'city' => 'Curitiba']);
    $ana = Tenant::factory()->for($account, 'account')->create(['name' => 'Ana Pereira']);
    $carlos = Tenant::factory()->for($account, 'account')->create(['name' => 'Carlos Lima']);

    Lease::factory()->for($account, 'account')->for($property, 'property')->for($ana, 'tenant')->create();
    Lease::factory()->ended()->for($account, 'account')->for($property, 'property')->for($carlos, 'tenant')->create();

    $this->actingAs($user)
        ->get(route('leases.index', ['status' => 'ended']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('leases.data', 1)
            ->where('leases.data.0.tenant.name', 'Carlos Lima')
        );

    $this->actingAs($user)
        ->get(route('leases.index', ['search' => 'Ana']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('leases.data', 1)
            ->where('leases.data.0.tenant.name', 'Ana Pereira')
        );
});

test('creating a lease marks the property as rented', function () {
    ['account' => $account, 'user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();

    $this->actingAs($user)
        ->post(route('leases.store'), validLeasePayload($property, $tenant))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('leases.index'));

    $this->assertDatabaseHas('leases', [
        'account_id' => $account->id,
        'property_id' => $property->id,
        'tenant_id' => $tenant->id,
        'status' => LeaseStatus::Active->value,
        'due_day' => 10,
        'notes' => 'Pagamento via Pix.',
    ]);
    expect($property->fresh()->status)->toBe(PropertyStatus::Rented);
});

test('a deposit guarantee requires the deposit amount', function () {
    ['user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();

    $this->actingAs($user)
        ->post(route('leases.store'), validLeasePayload($property, $tenant, ['guarantee_type' => 'deposit']))
        ->assertSessionHasErrors('deposit_amount');

    $this->actingAs($user)
        ->post(route('leases.store'), validLeasePayload($property, $tenant, [
            'guarantee_type' => 'deposit',
            'deposit_amount' => '3600.00',
        ]))
        ->assertSessionHasNoErrors();

    expect(Lease::first()->deposit_amount)->toBe('3600.00');
});

test('the deposit amount is ignored for other guarantee types', function () {
    ['user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();

    $this->actingAs($user)
        ->post(route('leases.store'), validLeasePayload($property, $tenant, [
            'guarantee_type' => 'guarantor',
            'guarantor' => ['name' => 'José Fiador'],
            'deposit_amount' => '3600.00',
        ]))
        ->assertSessionHasNoErrors();

    expect(Lease::first()->deposit_amount)->toBeNull();
});

test('a lease requires an end date after the start date', function () {
    ['user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();

    $this->actingAs($user)
        ->post(route('leases.store'), validLeasePayload($property, $tenant, ['end_date' => '2025-12-31']))
        ->assertSessionHasErrors('end_date');
});

test('a lease cannot be created for a property that is not available', function () {
    ['user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();
    $property->update(['status' => PropertyStatus::Rented]);

    $this->actingAs($user)
        ->post(route('leases.store'), validLeasePayload($property, $tenant))
        ->assertSessionHasErrors('property_id');

    expect(Lease::count())->toBe(0);
});

test('a lease cannot use a property or tenant from another account', function () {
    ['user' => $user] = leaseScenario();
    $otherProperty = Property::factory()->create();
    $otherTenant = Tenant::factory()->create();

    $this->actingAs($user)
        ->post(route('leases.store'), validLeasePayload($otherProperty, $otherTenant))
        ->assertSessionHasErrors(['property_id', 'tenant_id']);
});

test('updating a lease to another property moves the rented status', function () {
    ['account' => $account, 'user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();
    $property->update(['status' => PropertyStatus::Rented]);
    $newProperty = Property::factory()->for($account, 'account')->for($property->owner, 'owner')->create();
    $lease = Lease::factory()->for($account, 'account')->for($property, 'property')->for($tenant, 'tenant')->create();

    $this->actingAs($user)
        ->put(route('leases.update', $lease), validLeasePayload($newProperty, $tenant, ['amount' => '2000.00']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('leases.index'));

    expect($lease->fresh()->property_id)->toBe($newProperty->id)
        ->and($lease->fresh()->amount)->toBe('2000.00')
        ->and($property->fresh()->status)->toBe(PropertyStatus::Available)
        ->and($newProperty->fresh()->status)->toBe(PropertyStatus::Rented);
});

test('an active lease can be updated keeping its own rented property', function () {
    ['account' => $account, 'user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();
    $property->update(['status' => PropertyStatus::Rented]);
    $lease = Lease::factory()->for($account, 'account')->for($property, 'property')->for($tenant, 'tenant')->create();

    $this->actingAs($user)
        ->put(route('leases.update', $lease), validLeasePayload($property, $tenant, ['due_day' => 5]))
        ->assertSessionHasNoErrors();

    expect($lease->fresh()->due_day)->toBe(5);
});

test('finished leases cannot be updated', function () {
    ['account' => $account, 'user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();
    $lease = Lease::factory()->ended()->for($account, 'account')->for($property, 'property')->for($tenant, 'tenant')->create();

    $this->actingAs($user)
        ->put(route('leases.update', $lease), validLeasePayload($property, $tenant))
        ->assertForbidden();
});

test('finishing a lease frees its property', function (string $status) {
    ['account' => $account, 'user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();
    $property->update(['status' => PropertyStatus::Rented]);
    $lease = Lease::factory()->for($account, 'account')->for($property, 'property')->for($tenant, 'tenant')->create();

    $this->actingAs($user)
        ->patch(route('leases.finish', $lease), ['status' => $status])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('leases.index'));

    expect($lease->fresh()->status->value)->toBe($status)
        ->and($property->fresh()->status)->toBe(PropertyStatus::Available);
})->with(['ended', 'terminated']);

test('a lease cannot be finished as active', function () {
    ['account' => $account, 'user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();
    $lease = Lease::factory()->for($account, 'account')->for($property, 'property')->for($tenant, 'tenant')->create();

    $this->actingAs($user)
        ->patch(route('leases.finish', $lease), ['status' => 'active'])
        ->assertSessionHasErrors('status');
});

test('deleting an active lease frees its property', function () {
    ['account' => $account, 'user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();
    $property->update(['status' => PropertyStatus::Rented]);
    $lease = Lease::factory()->for($account, 'account')->for($property, 'property')->for($tenant, 'tenant')->create();

    $this->actingAs($user)
        ->delete(route('leases.destroy', $lease))
        ->assertRedirect(route('leases.index'));

    expect($lease->fresh()->trashed())->toBeTrue()
        ->and($property->fresh()->status)->toBe(PropertyStatus::Available);
});

test('a user cannot manage a lease from another account', function () {
    ['user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();
    $otherLease = Lease::factory()->create();

    $this->actingAs($user)->put(route('leases.update', $otherLease), validLeasePayload($property, $tenant))->assertNotFound();
    $this->actingAs($user)->patch(route('leases.finish', $otherLease), ['status' => 'ended'])->assertNotFound();
    $this->actingAs($user)->delete(route('leases.destroy', $otherLease))->assertNotFound();

    expect($otherLease->fresh()->isActive())->toBeTrue();
});

test('tenants and properties with an active lease cannot be deleted', function () {
    ['account' => $account, 'user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();
    Lease::factory()->for($account, 'account')->for($property, 'property')->for($tenant, 'tenant')->create();

    $this->actingAs($user)->delete(route('tenants.destroy', $tenant));
    $this->actingAs($user)->delete(route('properties.destroy', $property));

    expect($tenant->fresh()->trashed())->toBeFalse()
        ->and($property->fresh()->trashed())->toBeFalse();
});

test('tenants and properties with only finished leases can be deleted', function () {
    ['account' => $account, 'user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();
    Lease::factory()->ended()->for($account, 'account')->for($property, 'property')->for($tenant, 'tenant')->create();

    $this->actingAs($user)->delete(route('tenants.destroy', $tenant));
    $this->actingAs($user)->delete(route('properties.destroy', $property));

    expect($tenant->fresh()->trashed())->toBeTrue()
        ->and($property->fresh()->trashed())->toBeTrue();
});

test('tenants who still owe payments cannot be deleted, even with only finished leases', function (PaymentStatus $status, bool $deletable) {
    ['account' => $account, 'user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();
    $lease = Lease::factory()->ended()->for($account, 'account')->for($property, 'property')->for($tenant, 'tenant')->create();
    Payment::factory()->for($lease)->for($account, 'account')->create(['status' => $status]);

    $this->actingAs($user)->delete(route('tenants.destroy', $tenant));

    expect($tenant->fresh()->trashed())->toBe($deletable);
})->with([
    'pendente' => [PaymentStatus::Pending, false],
    'parcial' => [PaymentStatus::Partial, false],
    'paga' => [PaymentStatus::Paid, true],
    'cancelada' => [PaymentStatus::Canceled, true],
]);

test('index can preselect a lease of the account', function () {
    ['account' => $account, 'user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();
    $lease = Lease::factory()->for($account, 'account')->for($property, 'property')->for($tenant, 'tenant')->create();
    $otherLease = Lease::factory()->create();

    $this->actingAs($user)
        ->get(route('leases.index', ['show' => $lease->id]))
        ->assertInertia(fn (Assert $page) => $page->where('selected.id', $lease->id));

    $this->actingAs($user)
        ->get(route('leases.index', ['show' => $otherLease->id]))
        ->assertInertia(fn (Assert $page) => $page->where('selected', null));
});

test('index sorts leases by tenant name by default', function () {
    ['account' => $account, 'user' => $user, 'property' => $property] = leaseScenario();
    $otherProperty = Property::factory()->for($account, 'account')->for($property->owner, 'owner')->create();
    $bruno = Tenant::factory()->for($account, 'account')->create(['name' => 'Bruno']);
    $ana = Tenant::factory()->for($account, 'account')->create(['name' => 'Ana']);
    Lease::factory()->for($account, 'account')->for($property, 'property')->for($bruno, 'tenant')->create();
    Lease::factory()->for($account, 'account')->for($otherProperty, 'property')->for($ana, 'tenant')->create();

    $this->actingAs($user)
        ->get(route('leases.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'tenant')
            ->where('leases.data.0.tenant.name', 'Ana')
            ->where('leases.data.1.tenant.name', 'Bruno')
        );
});

test('a lease uses the default contract terms when they are omitted', function () {
    ['user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();

    $this->actingAs($user)
        ->post(route('leases.store'), validLeasePayload($property, $tenant))
        ->assertSessionHasNoErrors();

    $lease = Lease::first();

    expect($lease->purpose)->toBe(LeasePurpose::Residential)
        ->and($lease->adjustment_index)->toBe(AdjustmentIndex::Igpm)
        ->and($lease->late_fee_percent)->toBe('10.00')
        ->and($lease->monthly_interest_percent)->toBe('1.00')
        ->and($lease->termination_fee_months)->toBe(3);
});

test('a lease validates its contract terms', function () {
    ['user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();

    $this->actingAs($user)
        ->post(route('leases.store'), validLeasePayload($property, $tenant, [
            'purpose' => 'industrial',
            'adjustment_index' => 'selic',
            'late_fee_percent' => '150',
            'monthly_interest_percent' => '-1',
            'termination_fee_months' => '13',
        ]))
        ->assertSessionHasErrors(['purpose', 'adjustment_index', 'late_fee_percent', 'monthly_interest_percent', 'termination_fee_months']);

    expect(Lease::count())->toBe(0);
});

test('a lease guaranteed by a guarantor stores the guarantor', function () {
    ['user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();

    $this->actingAs($user)
        ->post(route('leases.store'), validLeasePayload($property, $tenant, [
            'guarantee_type' => GuaranteeType::Guarantor->value,
            'guarantor' => ['name' => ''],
        ]))
        ->assertSessionHasErrors('guarantor.name');

    $this->actingAs($user)
        ->post(route('leases.store'), validLeasePayload($property, $tenant, [
            'purpose' => 'commercial',
            'guarantee_type' => GuaranteeType::Guarantor->value,
            'guarantor' => [
                'name' => 'José Fiador',
                'cpf_cnpj' => '98765432100',
                'marital_status' => 'married',
                'spouse_name' => 'Ana Fiadora',
                'property_registration' => 'Matrícula 12.345',
                'state' => 'PR',
            ],
        ]))
        ->assertSessionHasNoErrors();

    $lease = Lease::first();

    expect($lease->purpose)->toBe(LeasePurpose::Commercial)
        ->and($lease->guarantor->name)->toBe('José Fiador')
        ->and($lease->guarantor->marital_status)->toBe(MaritalStatus::Married)
        ->and($lease->guarantor->spouse_name)->toBe('Ana Fiadora');
});

test('changing the guarantee type removes the guarantor and the surety details', function () {
    ['account' => $account, 'user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();
    $property->update(['status' => PropertyStatus::Rented]);
    $lease = Lease::factory()->withGuarantor()->for($account, 'account')->for($property, 'property')->for($tenant, 'tenant')->create();

    expect($lease->guarantor)->not->toBeNull();

    $this->actingAs($user)
        ->put(route('leases.update', $lease), validLeasePayload($property, $tenant, [
            'guarantee_type' => GuaranteeType::SuretyBond->value,
            'surety_insurer' => 'Seguradora X',
            'surety_policy_number' => 'AP-123',
            'guarantor' => ['name' => 'Ignorado'],
        ]))
        ->assertSessionHasNoErrors();

    $lease->refresh();

    expect($lease->guarantor)->toBeNull()
        ->and($lease->surety_insurer)->toBe('Seguradora X')
        ->and($lease->surety_policy_number)->toBe('AP-123');

    $this->actingAs($user)
        ->put(route('leases.update', $lease), validLeasePayload($property, $tenant, [
            'guarantee_type' => GuaranteeType::Deposit->value,
            'deposit_amount' => '3600.00',
            'surety_insurer' => 'Seguradora X',
        ]))
        ->assertSessionHasNoErrors();

    expect($lease->fresh()->surety_insurer)->toBeNull()
        ->and($lease->fresh()->surety_policy_number)->toBeNull();
});

test('index includes the lease guarantor', function () {
    ['account' => $account, 'user' => $user, 'property' => $property, 'tenant' => $tenant] = leaseScenario();
    Lease::factory()->withGuarantor()->for($account, 'account')->for($property, 'property')->for($tenant, 'tenant')->create();

    $this->actingAs($user)
        ->get(route('leases.index'))
        ->assertInertia(fn (Assert $page) => $page->has('leases.data.0.guarantor.name'));
});
