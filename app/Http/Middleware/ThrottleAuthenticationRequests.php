<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Throttle the Fortify routes Fortify itself leaves open: signing up, asking for and using a password
 * reset link, and confirming the password. The rate limiters live in FortifyServiceProvider.
 */
class ThrottleAuthenticationRequests
{
    /**
     * The rate limiter of each throttled route, by route name.
     *
     * @var array<string, string>
     */
    private const array LIMITERS = [
        'register.store' => 'register',
        'password.email' => 'password-reset',
        'password.update' => 'password-reset',
        'password.confirm.store' => 'password-confirmation',
    ];

    public function __construct(private ThrottleRequests $throttle) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::LIMITERS as $route => $limiter) {
            if ($request->routeIs($route)) {
                return $this->throttle->handle($request, $next, $limiter);
            }
        }

        return $next($request);
    }
}
