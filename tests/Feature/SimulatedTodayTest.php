<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('pages receive the app date, which the test suite never simulates', function () {
    $this->travelTo('2026-10-15 09:00:00');
    config(['app.fake_today' => '2027-01-01']);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('today', '2026-10-15')
            ->where('isSimulatedToday', false)
        );
});
