<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Symfony\Component\Finder\Finder;

/*
 * Architecture rule for notification links (issue #39).
 *
 * `TeamCreatedNotification` pointed at `admin.interclub.teams.show`, where the
 * route is `admin.interclubs.teams.show` — interclubs, plural. A missing route
 * name throws at construction, so the notification did not merely link nowhere:
 * it broke the send outright. Nothing caught it because no test ever built that
 * notification.
 *
 * The rule is cheap to hold: every route name a notification hands to `route()`
 * must exist. It lives in Feature rather than Architecture because resolving a
 * route name needs the application booted, which the Architecture suite does
 * not do — same reason as ComponentsArchTest next door.
 *
 * Names built from a variable are skipped: none exist today, and a static check
 * could not resolve them anyway.
 */
it('links notifications to routes that exist', function (): void {
    $files = (new Finder)
        ->files()
        ->in(app_path())
        ->path('Notifications')
        ->name('*.php');

    $offenders = [];

    foreach ($files as $file) {
        preg_match_all("/route\(\s*'([a-zA-Z0-9._-]+)'/", $file->getContents(), $matches);

        foreach ($matches[1] as $name) {
            if (! Route::has($name)) {
                $offenders[] = $file->getRelativePathname() . " → {$name}";
            }
        }
    }

    expect($offenders)->toBe([]);
});
