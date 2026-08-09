<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rate limits and CORS on endpoints an anonymous visitor can reach
|--------------------------------------------------------------------------
|
| The password broker already throttles one address to one mail a minute
| (config/auth.php), but nothing stopped a caller walking a list of addresses:
| the difference between "mail sent" and "we don't know that address" is enough
| to enumerate the roster, and the mails land in real members' inboxes.
|
| CORS answered `*` for every origin. Harmless while the only API route sits
| behind auth:sanctum and credentials are off, but it is the wrong default to
| leave for whoever adds the second route.
|
*/

describe('password routes', function (): void {

    it('rate-limits the routes that send or accept a reset', function (string $routeName): void {
        $middleware = Route::getRoutes()->getByName($routeName)?->gatherMiddleware();

        expect($middleware)->toContain('throttle:6,1');
    })->with([
        'password.email',
        'password.store',
    ]);

    it('locks out the sixth reset request from one address', function (): void {
        foreach (range(1, 6) as $ignored) {
            $this->post(route('password.email'), ['email' => 'nobody@example.test']);
        }

        $this->post(route('password.email'), ['email' => 'nobody@example.test'])
            ->assertTooManyRequests();
    });

})->group('security');

describe('CORS', function (): void {

    it('does not answer every origin', function (): void {
        expect(config('cors.allowed_origins'))->not->toContain('*');
    });

    it('allows the application origin', function (): void {
        expect(config('cors.allowed_origins'))->toContain(config('app.url'));
    });

})->group('security');
