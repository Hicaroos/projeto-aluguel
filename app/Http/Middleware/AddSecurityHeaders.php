<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Headers every response carries, unless it sets its own: no framing by other sites, no MIME
     * sniffing of uploaded files, only the origin in the referrer sent to other sites, and no
     * camera, microphone or location for any page.
     *
     * @var array<string, string>
     */
    private const array HEADERS = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    /**
     * The content policy of the pages. Files such as PDFs go without it, so the browser's viewer opens them.
     */
    private const string PAGE_POLICY = "frame-ancestors 'self'; base-uri 'self'; object-src 'none'";

    /**
     * How long browsers keep to HTTPS after seeing the site over it: a year.
     */
    private const string STRICT_TRANSPORT = 'max-age=31536000; includeSubDomains';

    /**
     * Add the security headers to the response.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        foreach (self::HEADERS as $name => $value) {
            if (! $headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        if (str_starts_with((string) $headers->get('Content-Type'), 'text/html') && ! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', self::PAGE_POLICY);
        }

        if ($request->isSecure() && ! $headers->has('Strict-Transport-Security')) {
            $headers->set('Strict-Transport-Security', self::STRICT_TRANSPORT);
        }

        return $response;
    }
}
