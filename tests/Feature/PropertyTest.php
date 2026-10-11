<?php

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function validPropertyPayload(array $overrides = []): array
{
    return array_merge([
        'type' => PropertyType::House->value,
        'zip_code' => '12345-000',
        'street' => 'Rua Teste',
        'number' => '100',
        'neighborhood' => 'Centro',
        'city' => 'São Paulo',
        'state' => 'SP',
        'rent_amount' => '1500.00',
        'status' => PropertyStatus::Available->value,
    ], $overrides);
}

test('index only lists properties belonging to the authenticated account', function () {
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();
    Property::factory()->for($account, 'account')->for($owner, 'owner')->count(2)->create();

    $otherAccount = Account::factory()->create();
    $otherOwner = Owner::factory()->for($otherAccount, 'account')->create();
    Property::factory()->for($otherAccount, 'account')->for($otherOwner, 'owner')->create();

    $this->actingAs($user)
        ->get(route('properties.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('properties/Index')
            ->has('properties.data', 2)
        );
});

test('single owner accounts automatically get their own owner assigned', function () {
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();

    $response = $this->actingAs($user)->post(route('properties.store'), validPropertyPayload());

    $response->assertSessionHasNoErrors()->assertRedirect(route('properties.index'));

    $this->assertDatabaseHas('properties', [
        'account_id' => $account->id,
        'owner_id' => $owner->id,
        'street' => 'Rua Teste',
    ]);
});

test('agency accounts can create a property for one of their own owners', function () {
    $account = Account::factory()->agency()->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $branch = Branch::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();

    $response = $this->actingAs($user)->post(
        route('properties.store'),
        validPropertyPayload(['owner_id' => $owner->id, 'branch_id' => $branch->id]),
    );

    $response->assertSessionHasNoErrors()->assertRedirect(route('properties.index'));

    $this->assertDatabaseHas('properties', [
        'account_id' => $account->id,
        'branch_id' => $branch->id,
        'owner_id' => $owner->id,
    ]);
});

test('agency accounts cannot assign a property to an owner from another account', function () {
    $account = Account::factory()->agency()->create();
    $user = User::factory()->for($account, 'account')->create();

    $otherAccount = Account::factory()->create();
    $otherOwner = Owner::factory()->for($otherAccount, 'account')->create();

    $response = $this->actingAs($user)->post(
        route('properties.store'),
        validPropertyPayload(['owner_id' => $otherOwner->id]),
    );

    $response->assertSessionHasErrors('owner_id');
    $this->assertDatabaseMissing('properties', ['owner_id' => $otherOwner->id]);
});

test('owner can update and delete their own property', function () {
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();
    $property = Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['city' => 'Old City']);

    $updateResponse = $this->actingAs($user)->put(
        route('properties.update', $property),
        validPropertyPayload(['city' => 'New City']),
    );

    $updateResponse->assertSessionHasNoErrors()->assertRedirect(route('properties.index'));
    expect($property->fresh()->city)->toBe('New City');

    $deleteResponse = $this->actingAs($user)->delete(route('properties.destroy', $property));

    $deleteResponse->assertRedirect(route('properties.index'));
    expect($property->fresh()->trashed())->toBeTrue();
});

test('a user cannot update or delete a property from another account', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();

    $otherAccount = Account::factory()->create();
    $otherOwner = Owner::factory()->for($otherAccount, 'account')->create();
    $otherProperty = Property::factory()->for($otherAccount, 'account')->for($otherOwner, 'owner')->create();

    $this->actingAs($user)->put(route('properties.update', $otherProperty), validPropertyPayload())->assertNotFound();
    $this->actingAs($user)->delete(route('properties.destroy', $otherProperty))->assertNotFound();

    expect($otherProperty->fresh())->not->toBeNull();
});

test('index filters properties by address search term', function () {
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();

    Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['city' => 'São Paulo']);
    Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['city' => 'Curitiba']);

    $this->actingAs($user)
        ->get(route('properties.index', ['search' => 'Curitiba']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('properties/Index')
            ->has('properties.data', 1)
            ->where('properties.data.0.city', 'Curitiba')
        );
});

test('index paginates properties', function () {
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();

    Property::factory()->for($account, 'account')->for($owner, 'owner')->count(11)->create();

    $this->actingAs($user)
        ->get(route('properties.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('properties/Index')
            ->has('properties.data', 10)
            ->where('properties.last_page', 2)
        );
});

test('the rented status cannot be set manually', function () {
    $account = Account::factory()->create();
    Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->post(route('properties.store'), validPropertyPayload(['status' => PropertyStatus::Rented->value]))
        ->assertSessionHasErrors('status');
});

test('updating a rented property keeps its rented status', function () {
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();
    $property = Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['status' => PropertyStatus::Rented]);

    $this->actingAs($user)
        ->put(route('properties.update', $property), validPropertyPayload(['status' => PropertyStatus::Available->value]))
        ->assertSessionHasNoErrors();

    expect($property->fresh()->status)->toBe(PropertyStatus::Rented);
});

test('index includes the active lease of rented properties and can preselect a property', function () {
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();
    $property = Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['status' => PropertyStatus::Rented]);
    $lease = Lease::factory()->for($account, 'account')->for($property)->create();
    $otherProperty = Property::factory()->create();

    $this->actingAs($user)
        ->get(route('properties.index', ['show' => $property->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('properties.data.0.active_lease.id', $lease->id)
            ->where('properties.data.0.active_lease.tenant.name', $lease->tenant->name)
            ->where('selected.id', $property->id)
        );

    $this->actingAs($user)
        ->get(route('properties.index', ['show' => $otherProperty->id]))
        ->assertInertia(fn (Assert $page) => $page->where('selected', null));
});

test('index sorts properties by street by default and by any allowed column on request', function () {
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();
    Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['street' => 'Rua B', 'rent_amount' => 900]);
    Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['street' => 'Rua C', 'rent_amount' => 3000]);
    Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['street' => 'Rua A', 'rent_amount' => 1500]);

    $this->actingAs($user)
        ->get(route('properties.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'street')
            ->where('filters.direction', 'asc')
            ->where('properties.data.0.street', 'Rua A')
            ->where('properties.data.2.street', 'Rua C')
        );

    $this->actingAs($user)
        ->get(route('properties.index', ['sort' => 'rent_amount', 'direction' => 'desc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('properties.data.0.street', 'Rua C')
            ->where('properties.data.2.street', 'Rua B')
        );

    $this->actingAs($user)
        ->get(route('properties.index', ['sort' => 'owner_id; drop table', 'direction' => 'sideways']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'street')
            ->where('filters.direction', 'asc')
        );
});

test('index filters properties by type and status', function () {
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();
    Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['type' => PropertyType::House, 'status' => PropertyStatus::Available]);
    Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['type' => PropertyType::Apartment, 'status' => PropertyStatus::Available]);
    Property::factory()->for($account, 'account')->for($owner, 'owner')->create(['type' => PropertyType::Apartment, 'status' => PropertyStatus::Inactive]);

    $this->actingAs($user)
        ->get(route('properties.index', ['type' => 'apartment', 'status' => 'available']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('filters.type', 'apartment')
            ->where('filters.status', 'available')
        );
});

test('properties can be offered only for sale, with their characteristics', function () {
    $account = Account::factory()->create();
    Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->post(route('properties.store'), validPropertyPayload([
            'type' => PropertyType::Farm->value,
            'for_rent' => '0',
            'for_sale' => '1',
            'rent_amount' => '',
            'sale_price' => '350000.00',
            'bedrooms' => '3',
            'suites' => '1',
            'bathrooms' => '2',
            'parking_spaces' => '4',
            'built_area' => '180.5',
            'land_area' => '5000',
            'land_front' => '50',
            'land_depth' => '100',
            'description' => 'Chácara com pomar e açude.',
        ]))
        ->assertSessionHasNoErrors();

    $property = Property::where('account_id', $account->id)->sole();

    expect($property->for_rent)->toBeFalse()
        ->and($property->for_sale)->toBeTrue()
        ->and($property->rent_amount)->toBeNull()
        ->and($property->sale_price)->toBe('350000.00')
        ->and($property->bedrooms)->toBe(3)
        ->and($property->land_area)->toBe('5000.00')
        ->and($property->land_front)->toBe('50.00')
        ->and($property->land_depth)->toBe('100.00')
        ->and($property->type)->toBe(PropertyType::Farm);
});

test('properties need a purpose and the price of each purpose', function () {
    $account = Account::factory()->create();
    Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->post(route('properties.store'), validPropertyPayload(['for_rent' => '0', 'for_sale' => '0']))
        ->assertSessionHasErrors(['for_rent' => 'Marque se o imóvel é para alugar, para vender ou os dois.']);

    $this->actingAs($user)
        ->post(route('properties.store'), validPropertyPayload(['for_rent' => '1', 'for_sale' => '1', 'rent_amount' => '', 'sale_price' => '']))
        ->assertSessionHasErrors([
            'rent_amount' => 'Informe o valor do aluguel.',
            'sale_price' => 'Informe o preço de venda.',
        ]);
});

test('index filters properties for rent or for sale', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();
    $forRent = Property::factory()->for($account, 'account')->create();
    $forSale = Property::factory()->for($account, 'account')->create(['for_rent' => false, 'for_sale' => true, 'rent_amount' => null, 'sale_price' => 200000]);

    $this->actingAs($user)
        ->get(route('properties.index', ['purpose' => 'sale']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.id', $forSale->id)
            ->where('filters.purpose', 'sale')
        );

    $this->actingAs($user)
        ->get(route('properties.index', ['purpose' => 'rent']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.id', $forRent->id)
        );
});

test('properties only for sale cannot be rented', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();
    $property = Property::factory()->for($account, 'account')->create(['for_rent' => false, 'for_sale' => true, 'rent_amount' => null, 'sale_price' => 200000]);
    $tenant = Tenant::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->post(route('leases.store'), [
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2028-06-30',
            'amount' => '1800.00',
            'due_day' => 10,
            'guarantee_type' => 'none',
        ])
        ->assertSessionHasErrors(['property_id' => 'Este imóvel não está disponível para locação.']);
});

test('properties only for sale stay out of the rentals', function () {
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();
    $forRent = Property::factory()->for($account, 'account')->create();
    Property::factory()->for($account, 'account')->create(['for_rent' => false, 'for_sale' => true, 'rent_amount' => null, 'sale_price' => 200000]);

    $this->actingAs($user)
        ->get(route('leases.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties', 1)
            ->where('properties.0.id', $forRent->id)
        );

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('vacantPropertiesCount', 1));
});

test('the sold status cannot be set manually', function () {
    $account = Account::factory()->create();
    Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->post(route('properties.store'), validPropertyPayload(['status' => PropertyStatus::Sold->value]))
        ->assertSessionHasErrors('status');
});
