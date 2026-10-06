<?php

use App\Enums\MaritalStatus;
use App\Models\Account;
use App\Models\Owner;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a single owner account can view and update its owner details', function () {
    $account = Account::factory()->create();
    $owner = Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->get(route('owner.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Owner')
            ->where('owner.id', $owner->id)
        );

    $this->actingAs($user)
        ->patch(route('owner.update'), [
            'rg' => '12.345.678-9',
            'nationality' => 'brasileiro',
            'marital_status' => 'divorced',
            'profession' => 'Comerciante',
            'city' => 'Curitiba',
            'state' => 'PR',
            'pix_key' => 'dono@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('owner.edit'));

    $owner->refresh();

    expect($owner->marital_status)->toBe(MaritalStatus::Divorced)
        ->and($owner->pix_key)->toBe('dono@example.com')
        ->and($owner->state)->toBe('PR');
});

test('the owner details validate the marital status and state', function () {
    $account = Account::factory()->create();
    Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();

    $this->actingAs($user)
        ->patch(route('owner.update'), ['marital_status' => 'unknown', 'state' => 'Paraná'])
        ->assertSessionHasErrors(['marital_status', 'state']);
});

test('agencies cannot use the single owner details page', function () {
    $account = Account::factory()->agency()->create();
    Owner::factory()->for($account, 'account')->create();
    $user = User::factory()->for($account, 'account')->create();

    $this->actingAs($user)->get(route('owner.edit'))->assertNotFound();
    $this->actingAs($user)->patch(route('owner.update'), ['pix_key' => 'x'])->assertNotFound();
});
