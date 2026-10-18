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
        $previousGuard = Auth::getDefaultDriver();

        Auth::shouldUse('visitor');

        try {
            return $next($request);
        } finally {
            Auth::shouldUse($previousGuard);
        }
    }
}
