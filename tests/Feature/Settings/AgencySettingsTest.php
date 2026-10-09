<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function agencyPayload(array $overrides = []): array
{
    return [
        'name' => 'Imobiliária Silva',
        'legal_name' => 'Silva Negócios Imobiliários Ltda.',
        'document' => '12.345.678/0001-95',
        'creci' => '1234-J',
        'phone' => '(87) 3874-1234',
        'email' => 'contato@silva.com.br',
        'zip_code' => '56200-000',
        'street' => 'Rua Central',
        'number' => '10',
        'neighborhood' => 'Centro',
        'city' => 'Ouricuri',
        'state' => 'PE',
        ...$overrides,
    ];
}

test('agency admins can see and update the agency details', function () {
    $user = User::factory()->for(Account::factory()->agency(), 'account')->create();

    $this->actingAs($user)
        ->get(route('agency.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Agency')
            ->where('agency.id', $user->account_id)
            ->missing('agency.logo_path')
        );

    $this->actingAs($user)
        ->patch(route('agency.update'), agencyPayload())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('agency.edit'));

    $this->assertDatabaseHas('accounts', [
        'id' => $user->account_id,
        'name' => 'Imobiliária Silva',
        'legal_name' => 'Silva Negócios Imobiliários Ltda.',
        'document' => '12345678000195',
        'phone' => '8738741234',
        'city' => 'Ouricuri',
    ]);
});

test('the agency CNPJ and phone are required', function () {
    $user = User::factory()->for(Account::factory()->agency(), 'account')->create();

    $this->actingAs($user)
        ->patch(route('agency.update'), agencyPayload(['document' => '123', 'phone' => '']))
        ->assertSessionHasErrors(['document', 'phone']);
});

test('single owners have no agency details', function () {
    $user = User::factory()->for(Account::factory(), 'account')->create();

    $this->actingAs($user)->get(route('agency.edit'))->assertForbidden();
    $this->actingAs($user)->patch(route('agency.update'), agencyPayload())->assertForbidden();
});

test('agency admins can upload, see and remove the logo', function () {
    Storage::fake(Account::LOGO_DISK);

    $user = User::factory()->for(Account::factory()->agency(), 'account')->create();

    $this->actingAs($user)
        ->post(route('agency.logo.update'), ['logo' => UploadedFile::fake()->create('logo.png', 100, 'image/png')])
        ->assertSessionHasNoErrors();

    $account = $user->account->refresh();

    expect($account->logo_path)->not->toBeNull()
        ->and($account->logo_url)->toStartWith(route('agency.logo'));
    Storage::disk(Account::LOGO_DISK)->assertExists($account->logo_path);

    $this->actingAs($user)->get(route('agency.logo'))->assertOk();

    $this->actingAs($user)->delete(route('agency.logo.destroy'))->assertRedirect(route('agency.edit'));

    Storage::disk(Account::LOGO_DISK)->assertMissing($account->logo_path);
    expect($account->refresh()->logo_path)->toBeNull();

    $this->actingAs($user)->get(route('agency.logo'))->assertNotFound();
});

test('the logo must be an image', function () {
    Storage::fake(Account::LOGO_DISK);

    $user = User::factory()->for(Account::factory()->agency(), 'account')->create();

    $this->actingAs($user)
        ->post(route('agency.logo.update'), ['logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf')])
        ->assertSessionHasErrors('logo');
});
