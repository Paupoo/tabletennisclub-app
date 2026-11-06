<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Notifications\WeeklySelectionsNotification;
use App\Domains\Shared\Enums\EmailTrigger;
use App\Mail\QueueStalledMail;
use App\Support\AutomaticEmails\AutomaticEmail;
use App\Support\AutomaticEmails\AutomaticEmailCatalogue;
use App\Support\AutomaticEmails\ScheduledRuns;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Symfony\Component\Finder\Finder;

/*
| The catalogue is written by hand, so these tests are what keeps it true: a
| new notification or mailable cannot ship without being listed or excluded by
| name, and a scheduled email cannot point at a task the schedule dropped.
*/

/**
 * Every notification and mailable the application defines.
 *
 * @return array<int, class-string>
 */
function automaticEmailCandidates(): array
{
    $app = dirname(__DIR__, 4) . '/app';
    $classes = [];

    foreach (Finder::create()->files()->in($app)->name('*.php')->contains('/extends\s+(Notification|Mailable)\b/') as $file) {
        $class = 'App\\' . str_replace('/', '\\', mb_substr($file->getRelativePathname(), 0, -4));

        if (class_exists($class) && (is_subclass_of($class, Notification::class) || is_subclass_of($class, Mailable::class))) {
            $classes[] = $class;
        }
    }

    sort($classes);

    return $classes;
}

it('finds the notifications and mailables to check', function (): void {
    expect(automaticEmailCandidates())->toContain(
        QueueStalledMail::class,
        WeeklySelectionsNotification::class,
    );
});

it('lists or excludes by name every notification and mailable', function (): void {
    $known = [
        ...array_map(fn (AutomaticEmail $email): string => $email->source, AutomaticEmailCatalogue::emails()),
        ...array_keys(AutomaticEmailCatalogue::excluded()),
    ];

    $missing = array_values(array_diff(automaticEmailCandidates(), $known));

    expect($missing)->toBeEmpty(
        "Add these to AutomaticEmailCatalogue::emails(), or to excluded() with the reason:\n- " . implode("\n- ", $missing),
    );
});

it('lists each email once, and never one it also excludes', function (): void {
    $sources = array_map(fn (AutomaticEmail $email): string => $email->source, AutomaticEmailCatalogue::emails());

    expect($sources)->toEqual(array_values(array_unique($sources)))
        ->and(array_intersect($sources, array_keys(AutomaticEmailCatalogue::excluded())))->toBeEmpty();
});

it('points at classes that exist', function (): void {
    $sources = [
        ...array_map(fn (AutomaticEmail $email): string => $email->source, AutomaticEmailCatalogue::emails()),
        ...array_keys(AutomaticEmailCatalogue::excluded()),
    ];

    expect(array_filter($sources, fn (string $class): bool => ! class_exists($class)))->toBeEmpty();
});

it('ties every scheduled email to a task the schedule still runs', function (): void {
    $scheduled = array_keys(app(ScheduledRuns::class)->eventsByCommand());

    foreach (AutomaticEmailCatalogue::emails() as $email) {
        if ($email->trigger !== EmailTrigger::Scheduled) {
            expect($email->commands)->toBeEmpty("{$email->source} is not scheduled but names a command")
                ->and($email->when)->not->toBeNull("{$email->source} does not say what sets it off");

            continue;
        }

        expect($email->commands)->not->toBeEmpty("{$email->source} is scheduled but names no command");

        foreach ($email->commands as $command) {
            expect($scheduled)->toContain($command);
        }
    }
});
