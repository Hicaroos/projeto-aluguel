<?php

use App\Models\Account;
use App\Models\Owner;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile page exposes the account name and owner phone/cpf', function () {
    $account = Account::factory()->create(['name' => 'My Account']);
    Owner::factory()->for($account, 'account')->create([
        'cpf_cnpj' => '123.456.789-00',
        'phone' => '11999999999',
    ]);
    $user = User::factory()->for($account, 'account')->create();

    $this
        ->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('account.name', 'My Account')
            ->where('owner.phone', '11999999999')
            ->where('owner.cpf_cnpj', '123.456.789-00')
        );
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('account name and owner phone can be updated, but cpf/cnpj cannot', function () {
    $account = Account::factory()->create(['name' => 'Old Name']);
    $owner = Owner::factory()->for($account, 'account')->create([
        'cpf_cnpj' => '123.456.789-00',
        'phone' => '11999999999',
    ]);
    $user = User::factory()->for($account, 'account')->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'account_name' => 'New Name',
            'owner_phone' => '11988887777',
            'owner_cpf_cnpj' => '999.999.999-99',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($account->fresh()->name)->toBe('New Name');
    expect($owner->fresh()->phone)->toBe('11988887777');
    expect($owner->fresh()->cpf_cnpj)->toBe('123.456.789-00');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
