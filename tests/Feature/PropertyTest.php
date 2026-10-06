<?php

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Account;
use App\Models\Lease;
use App\Models\Owner;
use App\Models\Property;
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
    $user = User::factory()->for($account, 'account')->create();

    $response = $this->actingAs($user)->post(
        route('properties.store'),
        validPropertyPayload(['owner_id' => $owner->id]),
    );

    $response->assertSessionHasNoErrors()->assertRedirect(route('properties.index'));

    $this->assertDatabaseHas('properties', [
        'account_id' => $account->id,
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
