<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
|--------------------------------------------------------------------------
| Page smoke test (parameterless routes)
|--------------------------------------------------------------------------
|
| Hits every named GET page route that has no route parameters and asserts a
| 200 response. New pages are picked up automatically via smokeableGetRoutes()
| (routes are enumerated at run time, once the application is booted — a Pest
| dataset cannot be used here because datasets resolve before routes load).
|
| Exception handling is disabled so a broken page surfaces its real exception
| (fast) instead of rendering a 500 debug page (which can hang in this env).
| Each failure records the status or exception so the whole app is checked in
| one pass.
|
| Routes that legitimately do not render a page are listed in
| smokeSkippedRouteNames() (tests/Pest.php) with a justification.
| Parameterised routes are covered by PageSmokeWithBindingTest.php.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()
        ->isAdmin()
        ->isCommitteeMember()
        ->create(['is_active' => true]);

    // Minimal realistic baseline so index pages relying on a season render.
    makeActiveSeason();
});

it('renders every parameterless page without error as an admin', function (): void {
    $routes = smokeableGetRoutes();

    expect($routes)->not->toBeEmpty();

    $this->withoutExceptionHandling();

    $failures = [];

    foreach ($routes as $name => $url) {
        try {
            $status = $this->actingAs($this->admin)->get($url)->status();

            if ($status !== 200) {
                $failures[$name] = sprintf('%s [%s] -> HTTP %d', $name, $url, $status);
            }
        } catch (Throwable $e) {
            $failures[$name] = sprintf('%s [%s] -> %s: %s', $name, $url, $e::class, $e->getMessage());
        }
    }

    expect($failures)->toBe([], "Pages did not return 200:\n" . implode("\n", $failures));
});
