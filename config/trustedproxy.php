<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The load balancers or CDN (e.g. Cloudflare) in front of the app, whose
    | X-Forwarded-* headers carry the visitor's IP and the HTTPS scheme. List
    | their IPs separated by commas, or use "*" when nothing else can reach
    | the app. Left empty, no proxy is trusted, except on Laravel Cloud,
    | which the framework trusts by itself.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
