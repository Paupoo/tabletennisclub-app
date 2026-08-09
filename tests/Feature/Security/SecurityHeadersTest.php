<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Security response headers
|--------------------------------------------------------------------------
|
| The application emitted none of these. They are what keeps a markdown
| escaping slip from becoming an exploit, so they matter even now that
| Markdown::safe() exists.
|
| The CSP ships in Report-Only. Livewire 4 and Alpine 3 inject inline script
| and Mary UI inline styles, so an enforcing policy without 'unsafe-inline'
| breaks the back office outright. Report-Only collects violations without
| blocking; tighten it once the reports are read, not before.
|
*/

it('sets the always-safe headers on a public page', function (string $header, string $value): void {
    $this->get(route('home'))->assertOk()->assertHeader($header, $value);
})->with([
    ['X-Content-Type-Options', 'nosniff'],
    ['X-Frame-Options', 'SAMEORIGIN'],
    ['Referrer-Policy', 'strict-origin-when-cross-origin'],
])->group('security');

it('reports CSP violations without enforcing them yet', function (): void {
    $response = $this->get(route('home'))->assertOk();

    $response->assertHeader('Content-Security-Policy-Report-Only');
    expect($response->headers->has('Content-Security-Policy'))->toBeFalse();
})->group('security');

it('does not send HSTS over plain HTTP', function (): void {
    // Sending it in local development would pin the browser to https://localhost
    // and lock the developer out of their own machine.
    $response = $this->get(route('home'))->assertOk();

    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
})->group('security');

it('sends HSTS over HTTPS', function (): void {
    config()->set('app.url', 'https://cttob.example');

    $this->get('https://cttob.example' . route('home', absolute: false))
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
})->group('security');
