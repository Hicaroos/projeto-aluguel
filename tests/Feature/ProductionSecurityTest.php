<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

afterEach(function () {
    // Trusted hosts are kept statically by Symfony; don't let one test's leak into the next.
    Request::setTrustedHosts([]);
});

test('outside local the app only answers its own host, so a reset link never points elsewhere', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->app['env'] = 'production';

    $this->get(route('password.request'))->assertOk();
    $this->post('http://atacante.example/forgot-password', ['email' => $user->email])->assertBadRequest();

    Notification::assertNotSentTo($user, ResetPassword::class);
});

test('in production links use https and the session cookie only travels over https', function () {
    expect(config('session.secure'))->toBeFalse()
        ->and(url('/dashboard'))->toBe('http://localhost/dashboard');

    $environment = getenv('APP_ENV');
    putenv('APP_ENV=production');
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';

    try {
        $this->refreshApplication();

        expect(app()->isProduction())->toBeTrue()
            ->and(config('session.secure'))->toBeTrue()
            ->and(url('/dashboard'))->toBe('https://localhost/dashboard');
    } finally {
        putenv("APP_ENV={$environment}");
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = $environment;
    }
});

test('the proxies to trust come from TRUSTED_PROXIES', function () {
    // The underscore keeps the address out of the showcase routes, which take any slug right after the domain.
    Route::get('/_test/visitor-ip', fn (Request $request): string => (string) $request->ip());
    $throughProxy = fn () => $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeader('X-Forwarded-For', '203.0.113.9')
        ->get('/_test/visitor-ip');

    $throughProxy()->assertSeeText('10.0.0.5');

    config(['trustedproxy.proxies' => '10.0.0.5']);

    $throughProxy()->assertSeeText('203.0.113.9');
});
