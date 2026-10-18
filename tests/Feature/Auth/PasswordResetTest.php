<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('a reset request answers the same whether the e-mail has an account or not', function () {
    Notification::fake();
    $user = User::factory()->create();

    $known = $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email]);
    $unknown = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'ninguem@example.com']);

    foreach ([$known, $unknown] as $response) {
        $response->assertRedirect(route('password.request'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));
    }
});

test('a member with a pending invitation gets no reset link, which would bypass the invitation', function () {
    Notification::fake();
    $member = User::factory()->invited()->create();

    $this->post(route('password.email'), ['email' => $member->email])
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertNothingSentTo($member);
});

test('reset link requests from one address are rate limited', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('password.email'), ['email' => "pessoa{$attempt}@example.com"])
            ->assertSessionHas('status');
    }

    $this->post(route('password.email'), ['email' => 'pessoa6@example.com'])->assertTooManyRequests();
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});
