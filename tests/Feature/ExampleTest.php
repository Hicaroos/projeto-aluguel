<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests see the welcome page, with the sales WhatsApp its buttons open', function () {
    config(['app.sales_whatsapp' => '87991234567']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('salesWhatsapp', '87991234567'));
});

test('the welcome page tells search engines and link previews what the app is', function () {
    $this->get(route('home'))
        ->assertSee('<meta name="description" content="Cobrança, multa e juros, repasse e extrato do proprietário num sistema só', escape: false)
        ->assertSee('<link rel="canonical" href="'.route('home').'">', escape: false);
});

test('the welcome page keeps its light look whatever theme the visitor picked in the app', function () {
    $this->withUnencryptedCookie('appearance', 'dark')
        ->get(route('home'))
        ->assertSee('data-light-look', escape: false)
        ->assertDontSee('class="dark"', escape: false);
});

test('authenticated users are sent from the home page to the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
