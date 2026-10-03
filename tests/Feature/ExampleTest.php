<?php

use App\Models\User;

test('guests are sent from the home page to the login page', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('authenticated users are sent from the home page to the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
