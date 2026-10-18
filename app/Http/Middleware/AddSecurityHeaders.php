<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
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
     * Where the pages may load from: our own scripts (plus inline ones carrying the nonce), the
     * showcase fonts, the ZIP code lookup and the Google map of the property.
     *
     * @var array<string, list<string>>
     */
    private const array FULL_PAGE_POLICY = [
        'default-src' => ["'self'"],
        'script-src' => ["'self'"],
        'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.bunny.net'],
        'font-src' => ["'self'", 'data:', 'https://fonts.bunny.net'],
        'img-src' => ["'self'", 'data:', 'blob:'],
        'connect-src' => ["'self'", 'https://viacep.com.br'],
        'frame-src' => ['https://www.google.com'],
        'form-action' => ["'self'"],
        'frame-ancestors' => ["'self'"],
        'base-uri' => ["'self'"],
        'object-src' => ["'none'"],
    ];

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
        $nonce = Vite::useCspNonce();

        $response = $next($request);
        $headers = $response->headers;

        foreach (self::HEADERS as $name => $value) {
            if (! $headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        if (str_starts_with((string) $headers->get('Content-Type'), 'text/html') && ! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', self::PAGE_POLICY);

            // Only reported for now, in the browser console, until the pages are seen to keep to it.
            // The Vite dev server is left out: it serves from another address, which would be reported throughout.
            if (! Vite::isRunningHot()) {
                $headers->set('Content-Security-Policy-Report-Only', $this->fullPagePolicy($nonce));
            }
        }

        if ($request->isSecure() && ! $headers->has('Strict-Transport-Security')) {
            $headers->set('Strict-Transport-Security', self::STRICT_TRANSPORT);
        }

        return $response;
    }

    /**
     * Get the full page policy, letting in the inline scripts that carry the nonce of this response.
     */
    private function fullPagePolicy(string $nonce): string
    {
        $policy = self::FULL_PAGE_POLICY;
        $policy['script-src'][] = "'nonce-{$nonce}'";

        return collect($policy)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', $sources))
            ->implode('; ');
    }
}
