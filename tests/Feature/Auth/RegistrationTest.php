<?php

use App\Enums\AccountType;
use App\Models\Owner;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

/**
 * @return array<string, string>
 */
function registrationPayload(array $overrides = []): array
{
    return [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'account_type' => 'single_owner',
        'owner_cpf_cnpj' => '123.456.789-00',
        'owner_phone' => '(87) 99123-4567',
        ...$overrides,
    ];
}

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('single owners register as the owner of their properties, with the account named after them', function () {
    $response = $this->post(route('register.store'), registrationPayload());

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::whereEmail('test@example.com')->firstOrFail();

    $this->assertDatabaseHas('accounts', [
        'id' => $user->account_id,
        'name' => 'Test User',
        'type' => AccountType::SingleOwner->value,
    ]);

    $this->assertDatabaseHas('owners', [
        'account_id' => $user->account_id,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => '12345678900',
        'phone' => '87991234567',
    ]);
});

test('single owners must give their document and mobile phone', function (string $field) {
    $response = $this->post(route('register.store'), registrationPayload([$field => '']));

    $response->assertSessionHasErrors($field);
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
})->with(['owner_cpf_cnpj', 'owner_phone']);

test('agencies register with their name, CNPJ, CRECI and phone, without an owner', function () {
    $response = $this->post(route('register.store'), registrationPayload([
        'name' => 'Agency Admin',
        'email' => 'agency@example.com',
        'account_type' => 'agency',
        'account_name' => 'Imobiliária Silva',
        'owner_cpf_cnpj' => '',
        'owner_phone' => '',
        'agency_document' => '12.345.678/0001-95',
        'agency_creci' => '1234-J',
        'agency_phone' => '(87) 3874-1234',
    ]));

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::whereEmail('agency@example.com')->firstOrFail();

    $this->assertDatabaseHas('accounts', [
        'id' => $user->account_id,
        'name' => 'Imobiliária Silva',
        'type' => AccountType::Agency->value,
        'document' => '12345678000195',
        'creci' => '1234-J',
        'phone' => '8738741234',
    ]);

    expect(Owner::where('account_id', $user->account_id)->count())->toBe(0);
});

test('agencies must give their name, a valid CNPJ and a phone; the CRECI is optional', function (string $field, string $value) {
    $response = $this->post(route('register.store'), registrationPayload([
        'account_type' => 'agency',
        'account_name' => 'Imobiliária Silva',
        'agency_document' => '12345678000195',
        'agency_phone' => '8738741234',
        $field => $value,
    ]));

    $response->assertSessionHasErrors($field);
    $this->assertGuest();
})->with([
    'no name' => ['account_name', ''],
    'no CNPJ' => ['agency_document', ''],
    'a CPF as CNPJ' => ['agency_document', '123.456.789-00'],
    'no phone' => ['agency_phone', ''],
]);
