<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ActAsShowcaseVisitor
{
    /**
     * Serve the public showcase as to any visitor, even to someone signed in to the app: no account
     * scope narrows what it shows, and nothing it records lands in the signed in user's account.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return self::during(fn (): Response => $next($request));
    }

    /**
     * Run the callback as a visitor of the showcase, so it sees what anyone with the address sees,
     * not what the signed in user's branches or selected owner narrow it to.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public static function during(Closure $callback): mixed
    {
        $previousGuard = Auth::getDefaultDriver();

        Auth::shouldUse('visitor');

        try {
            return $callback();
        } finally {
            Auth::shouldUse($previousGuard);
        }
    }
}
