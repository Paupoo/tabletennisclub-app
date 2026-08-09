<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks must not overlap themselves
|--------------------------------------------------------------------------
|
| Both hourly deadline commands walk waiting lists and promote the next player
| in line. Two instances running at once — a slow run still going when the next
| hour fires — promote the same person twice and mail them twice.
|
| `onOneServer()` is deliberately not used: it needs a locking cache driver and
| buys nothing on a single machine.
|
*/

it('guards every scheduled command against overlapping itself', function (): void {
    $events = app(Schedule::class)->events();

    expect($events)->not->toBeEmpty();

    $unguarded = collect($events)
        ->reject(fn ($event): bool => $event->withoutOverlapping)
        ->map(fn ($event): string => $event->command ?? $event->description ?? 'unknown')
        ->map(fn (string $command): string => preg_replace('/^.*artisan[\'"]? /', '', $command) ?? $command)
        ->values()
        ->all();

    expect($unguarded)->toBe([]);
})->group('scheduling');
