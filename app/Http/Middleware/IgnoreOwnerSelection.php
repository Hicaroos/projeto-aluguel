<?php

namespace App\Http\Middleware;

use App\Models\Owner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IgnoreOwnerSelection
{
    /**
     * Show every owner on screens that don't belong to an owner, such as the sales funnel or the
     * agency management, whatever the owner selector says.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(Owner::IGNORES_SELECTION_ATTRIBUTE, true);

        return $next($request);
    }
}
