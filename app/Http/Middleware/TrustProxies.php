<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * The trusted proxies for this application.
     *
     * Deliberately empty. Apache serves PHP directly on the production VPS —
     * no `proxy_pass`, no CDN in front of the domain — so `REMOTE_ADDR` is
     * already the visitor and `$request->ip()` is correct. Trusting anything
     * here (`'*'` above all) would let a visitor forge `X-Forwarded-For` and
     * walk past every per-IP throttle, including the one on the public contact
     * form.
     *
     * Put a reverse proxy, a load balancer or Cloudflare in front and this
     * must be filled in with that proxy's addresses — otherwise every visitor
     * shares the proxy's IP and the throttles become one global counter.
     *
     * Pinned by tests/Feature/Security/TrustedHostsAndProxiesTest.php.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;
}
