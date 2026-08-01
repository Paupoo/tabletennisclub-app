<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the response headers that limit what a browser will do with our pages.
 *
 * These are the difference between an escaping slip and an exploit, which is
 * why they belong in the global stack rather than on the routes that happen to
 * render user-authored content today.
 *
 * The content security policy ships in **Report-Only** on purpose. Livewire
 * injects inline script, Alpine evaluates inline expressions, and Mary UI
 * emits inline styles, so an enforcing policy without `unsafe-inline` takes the
 * back office down. Report-Only reports violations and blocks nothing: read
 * what comes back, tighten the directives, and only then rename the header.
 */
class SecurityHeaders
{
    /**
     * Directives kept deliberately loose for Livewire, Alpine and Mary UI.
     *
     * `frame-ancestors 'none'` and `object-src 'none'` cost nothing and close
     * clickjacking and legacy plugin embedding; the rest is here to be
     * measured, not to be trusted yet.
     *
     * @var array<int, string>
     */
    private const CSP_DIRECTIVES = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob:",
        "font-src 'self' data:",
        "connect-src 'self'",
        "frame-ancestors 'none'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Content-Security-Policy-Report-Only',
            implode('; ', self::CSP_DIRECTIVES),
        );

        // Only over HTTPS: announcing it on a plain-HTTP development host pins
        // the browser to https://localhost and locks the developer out.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
