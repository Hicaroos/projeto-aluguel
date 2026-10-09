<?php

use App\Models\Account;
use App\Models\Guarantor;
use App\Models\Lease;
use App\Models\LeaseRenewal;
use App\Models\Owner;
use App\Models\Payment;
use App\Models\PropertyPhoto;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
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

test('updating the profile of a single owner account keeps the owner name and email in sync', function () {
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create(['name' => 'Nome Antigo']);
    $user = User::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Nome Novo',
            'email' => 'novo@example.com',
        ])
        ->assertSessionHasNoErrors();

    expect($owner->fresh()->name)->toBe('Nome Novo')
        ->and($owner->fresh()->email)->toBe('novo@example.com');
});

test('a single owner account can update the profile without the name, which is edited in the owner details', function () {
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create(['name' => 'Maria Dona']);
    $user = User::factory()->for($account, 'account')->create(['name' => 'Maria Dona']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['email' => 'maria@example.com', 'account_name' => 'Imóveis da Maria'])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Maria Dona')
        ->and($owner->fresh()->name)->toBe('Maria Dona')
        ->and($owner->fresh()->email)->toBe('maria@example.com');
});

test('updating the profile of an agency does not change its owners', function () {
    $account = Account::factory()->agency()->create();
    $owner = Owner::factory()->for($account, 'account')->create(['name' => 'Proprietário Cliente']);
    $user = User::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Corretor', 'email' => 'corretor@example.com'])
        ->assertSessionHasNoErrors();

    expect($owner->fresh()->name)->toBe('Proprietário Cliente');
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

test('deleting the only user of an account removes the whole account, its records and files', function () {
    Storage::fake(PropertyPhoto::DISK);
    $account = Account::factory()->create();
    $user = User::factory()->for($account, 'account')->create();
    $lease = Lease::factory()->for($account, 'account')->create();
    $payment = Payment::factory()->for($lease)->create();
    Receipt::factory()->for($payment)->create();
    Guarantor::factory()->for($lease)->create();
    LeaseRenewal::factory()->for($lease)->create();
    $photo = PropertyPhoto::factory()->for($lease->property)->create();
    Storage::disk(PropertyPhoto::DISK)->put($photo->path, 'photo');
    $otherLease = Lease::factory()->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertModelMissing($account);
    $this->assertDatabaseMissing('leases', ['account_id' => $account->id]);
    $this->assertDatabaseMissing('properties', ['account_id' => $account->id]);
    $this->assertDatabaseMissing('tenants', ['account_id' => $account->id]);
    $this->assertDatabaseMissing('payments', ['account_id' => $account->id]);
    $this->assertDatabaseMissing('receipts', ['account_id' => $account->id]);
    $this->assertDatabaseMissing('guarantors', ['lease_id' => $lease->id]);
    $this->assertDatabaseMissing('lease_renewals', ['lease_id' => $lease->id]);
    $this->assertModelExists($otherLease);
    Storage::disk(PropertyPhoto::DISK)->assertMissing($photo->path);
});

test('a user leaving an account with other users only removes themselves', function () {
    $account = Account::factory()->agency()->create();
    $user = User::factory()->for($account, 'account')->create();
    $colleague = User::factory()->for($account, 'account')->create();
    $lease = Lease::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertModelMissing($user);
    $this->assertModelExists($colleague);
    $this->assertModelExists($account);
    $this->assertModelExists($lease);
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
