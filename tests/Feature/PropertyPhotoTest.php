<?php

use App\Models\Account;
use App\Models\Property;
use App\Models\PropertyPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake(PropertyPhoto::DISK);
});

/**
 * @return array{user: User, property: Property}
 */
function photoScenario(): array
{
    $account = Account::factory()->create();

    return [
        'user' => User::factory()->for($account, 'account')->create(),
        'property' => Property::factory()->for($account, 'account')->create(),
    ];
}

function fakePhoto(string $name = 'foto.jpg', int $kilobytes = 300, string $mimeType = 'image/jpeg'): UploadedFile
{
    return UploadedFile::fake()->create($name, $kilobytes, $mimeType);
}

function storedPhoto(Property $property, int $sortOrder = 0): PropertyPhoto
{
    $path = "properties/{$property->id}/".fake()->uuid().'.jpg';
    Storage::disk(PropertyPhoto::DISK)->put($path, 'photo');

    return PropertyPhoto::factory()->for($property)->create(['path' => $path, 'sort_order' => $sortOrder]);
}

test('a photo and its thumbnail are stored privately after the existing photos', function () {
    ['user' => $user, 'property' => $property] = photoScenario();
    storedPhoto($property);

    $this->actingAs($user)
        ->from(route('properties.index', ['page' => 2]))
        ->post(route('properties.photos.store', $property), [
            'photo' => fakePhoto(),
            'thumbnail' => fakePhoto('miniatura.jpg', 40),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('properties.index', ['page' => 2, 'show' => $property->id]));

    $photo = PropertyPhoto::latest('id')->first();

    expect($photo->account_id)->toBe($property->account_id)
        ->and($photo->sort_order)->toBe(1)
        ->and($photo->path)->toStartWith("properties/{$property->id}/")
        ->and($photo->thumbnail_path)->not->toBeNull();

    Storage::disk(PropertyPhoto::DISK)->assertExists([$photo->path, $photo->thumbnail_path]);
});

test('only images up to 5 MB are accepted', function (UploadedFile $file) {
    ['user' => $user, 'property' => $property] = photoScenario();

    $this->actingAs($user)
        ->post(route('properties.photos.store', $property), ['photo' => $file])
        ->assertSessionHasErrors('photo');

    expect($property->photos()->count())->toBe(0);
})->with([
    'pdf' => fn () => fakePhoto('contrato.pdf', 300, 'application/pdf'),
    'too large' => fn () => fakePhoto('foto.jpg', 6000),
]);

test('a property can have at most 20 photos', function () {
    ['user' => $user, 'property' => $property] = photoScenario();
    PropertyPhoto::factory()->for($property)->count(PropertyPhoto::MAX_PER_PROPERTY)->create();

    $this->actingAs($user)
        ->post(route('properties.photos.store', $property), ['photo' => fakePhoto()])
        ->assertSessionHasErrors(['photo' => 'Cada imóvel pode ter no máximo 20 fotos.']);
});

test('photos can only be added to properties of the same account', function () {
    ['property' => $property] = photoScenario();

    $this->actingAs(User::factory()->create())
        ->post(route('properties.photos.store', $property), ['photo' => fakePhoto()])
        ->assertNotFound();
});

test('photos are served only to their own account, falling back to the full photo as thumbnail', function () {
    ['user' => $user, 'property' => $property] = photoScenario();
    $photo = storedPhoto($property);

    $this->actingAs($user)->get(route('property-photos.show', $photo))->assertOk();
    $this->actingAs($user)->get(route('property-photos.thumbnail', $photo))->assertOk();

    $this->actingAs(User::factory()->create())
        ->get(route('property-photos.show', $photo))
        ->assertNotFound();
});

test('making a photo the cover moves it to the front', function () {
    ['user' => $user, 'property' => $property] = photoScenario();
    $first = storedPhoto($property, 0);
    $second = storedPhoto($property, 1);
    $third = storedPhoto($property, 2);

    $this->actingAs($user)
        ->patch(route('property-photos.cover', $third))
        ->assertRedirect();

    expect($property->photos()->pluck('id')->all())->toBe([$third->id, $first->id, $second->id]);
});

test('removing a photo also deletes its files', function () {
    ['user' => $user, 'property' => $property] = photoScenario();
    $photo = storedPhoto($property);
    $thumbnail = "properties/{$property->id}/thumbnail.jpg";
    Storage::disk(PropertyPhoto::DISK)->put($thumbnail, 'thumbnail');
    $photo->update(['thumbnail_path' => $thumbnail]);

    $this->actingAs($user)
        ->delete(route('property-photos.destroy', $photo))
        ->assertRedirect();

    $this->assertModelMissing($photo);
    Storage::disk(PropertyPhoto::DISK)->assertMissing([$photo->path, $thumbnail]);
});

test('photos of another account cannot be changed or removed', function () {
    ['property' => $property] = photoScenario();
    $photo = storedPhoto($property);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->patch(route('property-photos.cover', $photo))->assertNotFound();
    $this->actingAs($intruder)->delete(route('property-photos.destroy', $photo))->assertNotFound();

    $this->assertModelExists($photo);
});

test('the properties list sends each property photos, cover first', function () {
    ['user' => $user, 'property' => $property] = photoScenario();
    $second = storedPhoto($property, 1);
    $cover = storedPhoto($property, 0);

    $this->actingAs($user)
        ->get(route('properties.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data.0.photos', 2)
            ->where('properties.data.0.photos.0.id', $cover->id)
            ->where('properties.data.0.photos.0.thumbnail_url', route('property-photos.thumbnail', $cover))
            ->where('properties.data.0.photos.1.id', $second->id)
            ->missing('properties.data.0.photos.0.path')
        );
});
