<?php

use App\Enums\AccountType;
use App\Models\Owner;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'account_type' => 'single_owner',
        'account_name' => 'Test Account',
        'owner_cpf_cnpj' => '123.456.789-00',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::whereEmail('test@example.com')->firstOrFail();

    expect($user->account_id)->not->toBeNull();

    $this->assertDatabaseHas('accounts', [
        'id' => $user->account_id,
        'name' => 'Test Account',
        'type' => AccountType::SingleOwner->value,
    ]);

    $this->assertDatabaseHas('owners', [
        'account_id' => $user->account_id,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => '123.456.789-00',
    ]);
});

test('new agencies can register without an owner', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Agency Admin',
        'email' => 'agency@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'account_type' => 'agency',
        'account_name' => 'Test Agency',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::whereEmail('agency@example.com')->firstOrFail();

    $this->assertDatabaseHas('accounts', [
        'id' => $user->account_id,
        'name' => 'Test Agency',
        'type' => AccountType::Agency->value,
    ]);

    expect(Owner::where('account_id', $user->account_id)->count())->toBe(0);
});

test('registration requires owner data for single_owner accounts', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'account_type' => 'single_owner',
        'account_name' => 'Test Account',
    ]);

    $response->assertSessionHasErrors('owner_cpf_cnpj');
    $this->assertGuest();

    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    $this->assertDatabaseMissing('accounts', ['name' => 'Test Account']);
});
