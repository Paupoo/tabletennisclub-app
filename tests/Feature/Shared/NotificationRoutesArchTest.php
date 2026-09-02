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

/*
 * The rule issue #39 was filed for.
 *
 * A member clicked a notification about their training pack and got a 403: the
 * link went to `admin.trainings.index`, gated by `can:trainings.manage`. Twenty
 * two notifications did the same, all of them addressed to the member the event
 * concerned, none of them to somebody holding the permission.
 *
 * A notification a member can receive must therefore not hard-code a route the
 * committee alone can open. Notifications that only ever reach the committee are
 * listed below with the reason — the list is the decision, not an escape hatch:
 * adding a line to it means asserting that no ordinary member is ever notified.
 */
it('never sends a member to a page the committee alone can open', function (): void {
    $committeeOnly = [
        // settings.php sends it to admins and the secretariat only —
        // UserSecurityActionsTest asserts a plain member never receives it.
        'GdprErasureRequestedNotification.php',
        // Both go to User::permission('payments.refund'): the action asked for is
        // the treasurer's, taken on the member's own file.
        'RefundRequestedNotification.php',
        'SubscriptionRefundRequestedNotification.php',
        // SendTeamCreatedNotification sends it to User::role('administrator').
        'TeamCreatedNotification.php',
    ];

    $files = (new Finder)
        ->files()
        ->in(app_path())
        ->path('Notifications')
        ->name('*.php');

    $offenders = [];

    foreach ($files as $file) {
        if (in_array($file->getFilename(), $committeeOnly, true)) {
            continue;
        }

        preg_match_all("/route\(\s*'([a-zA-Z0-9._-]+)'/", $file->getContents(), $matches);

        foreach ($matches[1] as $name) {
            $route = Route::getRoutes()->getByName($name);

            if ($route === null) {
                continue; // the test above already reports unknown names
            }

            $gates = array_filter(
                $route->gatherMiddleware(),
                fn (mixed $middleware): bool => is_string($middleware)
                    && (str_starts_with($middleware, 'can:') || str_starts_with($middleware, 'can.any:')),
            );

            if ($gates !== []) {
                $offenders[] = sprintf(
                    '%s → %s (%s)',
                    $file->getRelativePathname(),
                    $name,
                    implode(', ', $gates),
                );
            }
        }
    }

    expect($offenders)->toBe([]);
});
