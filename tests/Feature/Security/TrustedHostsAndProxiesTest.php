<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Trusted hosts and trusted proxies
|--------------------------------------------------------------------------
|
| TrustHosts was written but left commented out of the global stack, so the
| application answered to any Host header — enough to poison password-reset
| links and cache entries. It is inert in `local` and under tests by design
| (`shouldSpecifyTrustedHosts()`), hence the explicit production run below.
|
| TrustProxies stays unset on purpose: Apache serves PHP directly on the VPS,
| with no reverse proxy and no CDN in front, so REMOTE_ADDR is already the
| visitor. Trusting `*` there would let anyone forge X-Forwarded-For and walk
| past every per-IP throttle. The second block is what would break if a proxy
| were ever put in front without revisiting bootstrap/app.php.
|
| Both middleware are now the framework's own, enabled through
| `$middleware->trustHosts()`. The stack is read from the resolved kernel
| rather than from a class property, so it keeps checking the thing that
| actually runs.
|
*/

afterEach(function (): void {
    Request::setTrustedHosts([]);
});

describe('trusted hosts', function (): void {

    it('is registered in the global middleware stack', function (): void {
        $kernel = app(Kernel::class);

        $middleware = (new ReflectionProperty($kernel, 'middleware'))->getValue($kernel);

        expect($middleware)->toContain(TrustHosts::class);
    });

    it('trusts the application URL and its subdomains outside local', function (): void {
        config()->set('app.url', 'https://cttob.example');
        app()->detectEnvironment(fn (): string => 'production');

        (new TrustHosts(app()))->handle(Request::create('/'), fn (Request $r): Request => $r);

        expect(Request::getTrustedHosts())->toHaveCount(1)
            ->and(Request::getTrustedHosts()[0])->toMatch('/cttob\\\\.example/');
    });

})->group('security');

describe('client IP resolution', function (): void {

    it('ignores a forged X-Forwarded-For header', function (): void {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.99'])
            ->get('/');

        expect($response->baseRequest->ip())->toBe('203.0.113.7');
    });

})->group('security');
