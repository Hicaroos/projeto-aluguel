<?php

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Owner;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An agency with a branch and three owners.
 *
 * @return array{account: Account, user: User, branch: Branch, maria: Owner, joao: Owner, ana: Owner}
 */
function coOwnershipScenario(): array
{
    $account = Account::factory()->agency()->create();

    return [
        'account' => $account,
        'user' => User::factory()->for($account, 'account')->create(),
        'branch' => Branch::factory()->for($account, 'account')->create(),
        'maria' => Owner::factory()->for($account, 'account')->create(['name' => 'Maria']),
        'joao' => Owner::factory()->for($account, 'account')->create(['name' => 'João']),
        'ana' => Owner::factory()->for($account, 'account')->create(['name' => 'Ana']),
    ];
}

/**
 * @return array<string, mixed>
 */
function coOwnedPropertyPayload(array $overrides = []): array
{
    return [
        'type' => PropertyType::House->value,
        'zip_code' => '56200-000',
        'street' => 'Rua da Herança',
        'number' => '12',
        'neighborhood' => 'Centro',
        'city' => 'Ouricuri',
        'state' => 'PE',
        'rent_amount' => '1500.00',
        'status' => PropertyStatus::Available->value,
        ...$overrides,
    ];
}

/**
 * @return array<int, float>
 */
function sharesOf(Property $property): array
{
    return DB::table('owner_property')
        ->where('property_id', $property->id)
        ->orderBy('owner_id')
        ->pluck('share_percent', 'owner_id')
        ->map(fn (mixed $share): float => (float) $share)
        ->all();
}

test('new properties belong wholly to their main owner', function () {
    ['account' => $account, 'maria' => $maria] = coOwnershipScenario();

    $property = Property::factory()->for($account, 'account')->for($maria, 'owner')->create();

    expect(sharesOf($property))->toBe([$maria->id => 100.0])
        ->and($property->ownerShares())->toBe([$maria->id => 100.0]);
});

test('agencies register a property with co-owners, the main owner keeping the rest', function () {
    ['user' => $user, 'branch' => $branch, 'maria' => $maria, 'joao' => $joao, 'ana' => $ana] = coOwnershipScenario();

    $this->actingAs($user)
        ->post(route('properties.store'), coOwnedPropertyPayload([
            'branch_id' => $branch->id,
            'owner_id' => $maria->id,
            'co_owners' => [
                ['owner_id' => $joao->id, 'share_percent' => '25'],
                ['owner_id' => $ana->id, 'share_percent' => '25'],
            ],
        ]))
        ->assertSessionHasNoErrors();

    $property = Property::where('street', 'Rua da Herança')->sole();

    expect($property->owner_id)->toBe($maria->id)
        ->and(sharesOf($property))->toBe([$maria->id => 50.0, $joao->id => 25.0, $ana->id => 25.0]);

    foreach ([$maria, $joao, $ana] as $owner) {
        expect($owner->branches()->pluck('branches.id')->all())->toBe([$branch->id]);
    }
});

test('updating a property replaces its co-owners', function () {
    ['account' => $account, 'user' => $user, 'branch' => $branch, 'maria' => $maria, 'joao' => $joao, 'ana' => $ana] = coOwnershipScenario();
    $property = Property::factory()->for($account, 'account')->for($maria, 'owner')->create(['branch_id' => $branch->id]);

    $this->actingAs($user)
        ->put(route('properties.update', $property), coOwnedPropertyPayload([
            'branch_id' => $branch->id,
            'owner_id' => $maria->id,
            'co_owners' => [['owner_id' => $joao->id, 'share_percent' => '40']],
        ]))
        ->assertSessionHasNoErrors();

    expect(sharesOf($property))->toBe([$maria->id => 60.0, $joao->id => 40.0]);

    $this->actingAs($user)
        ->put(route('properties.update', $property), coOwnedPropertyPayload([
            'branch_id' => $branch->id,
            'owner_id' => $ana->id,
        ]))
        ->assertSessionHasNoErrors();

    expect(sharesOf($property))->toBe([$ana->id => 100.0]);
});

test('co-owners need valid shares that leave a part to the main owner', function () {
    ['user' => $user, 'branch' => $branch, 'maria' => $maria, 'joao' => $joao, 'ana' => $ana] = coOwnershipScenario();
    $stranger = Owner::factory()->create();
    $payload = fn (array $coOwners): array => coOwnedPropertyPayload([
        'branch_id' => $branch->id,
        'owner_id' => $maria->id,
        'co_owners' => $coOwners,
    ]);

    $this->actingAs($user)
        ->post(route('properties.store'), $payload([
            ['owner_id' => $joao->id, 'share_percent' => '60'],
            ['owner_id' => $ana->id, 'share_percent' => '40'],
        ]))
        ->assertSessionHasErrors('co_owners');

    $this->actingAs($user)
        ->post(route('properties.store'), $payload([['owner_id' => $maria->id, 'share_percent' => '50']]))
        ->assertSessionHasErrors('co_owners');

    $this->actingAs($user)
        ->post(route('properties.store'), $payload([
            ['owner_id' => $joao->id, 'share_percent' => '10'],
            ['owner_id' => $joao->id, 'share_percent' => '10'],
        ]))
        ->assertSessionHasErrors('co_owners.0.owner_id');

    $this->actingAs($user)
        ->post(route('properties.store'), $payload([['owner_id' => $stranger->id, 'share_percent' => '10']]))
        ->assertSessionHasErrors('co_owners.0.owner_id');

    $this->actingAs($user)
        ->post(route('properties.store'), $payload([['owner_id' => $joao->id, 'share_percent' => '0']]))
        ->assertSessionHasErrors('co_owners.0.share_percent');

    expect(Property::count())->toBe(0);
});

test('changing the main owner hands them the former main owner share', function () {
    ['account' => $account, 'maria' => $maria, 'joao' => $joao, 'ana' => $ana] = coOwnershipScenario();
    $property = Property::factory()->for($account, 'account')->for($maria, 'owner')->create();
    $property->owners()->sync([
        $maria->id => ['share_percent' => 70],
        $joao->id => ['share_percent' => 30],
    ]);

    $property->update(['owner_id' => $ana->id]);

    expect(sharesOf($property))->toBe([$joao->id => 30.0, $ana->id => 70.0]);
});

test('owners list the properties they own in part, and cannot be removed while they do', function () {
    ['account' => $account, 'user' => $user, 'maria' => $maria, 'joao' => $joao] = coOwnershipScenario();
    $property = Property::factory()->for($account, 'account')->for($maria, 'owner')->create();
    $property->owners()->sync([
        $maria->id => ['share_percent' => 50],
        $joao->id => ['share_percent' => 50],
    ]);

    $this->actingAs($user)
        ->get(route('owners.index', ['show' => $joao->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.properties_count', 1)
            ->where('selected.properties.0.id', $property->id)
            ->where('selected.properties.0.pivot.share_percent', '50.00')
        );

    $this->actingAs($user)->delete(route('owners.destroy', $joao));

    $this->assertNotSoftDeleted($joao);
});

test('property details show every owner with their share', function () {
    ['account' => $account, 'user' => $user, 'maria' => $maria, 'joao' => $joao] = coOwnershipScenario();
    $property = Property::factory()->for($account, 'account')->for($maria, 'owner')->create();
    $property->owners()->sync([
        $maria->id => ['share_percent' => 75],
        $joao->id => ['share_percent' => 25],
    ]);

    $this->actingAs($user)
        ->get(route('properties.index', ['show' => $property->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('selected.owners', 2)
            ->where('selected.owners', fn ($owners) => collect($owners)->pluck('pivot.share_percent', 'id')->all() === [
                $maria->id => '75.00',
                $joao->id => '25.00',
            ])
        );
});
