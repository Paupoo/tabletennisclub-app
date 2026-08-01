<?php

declare(strict_types=1);

use App\Mail\QueueStalledMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Mailables belong on the queue
|--------------------------------------------------------------------------
|
| A mailable that does not implement ShouldQueue is delivered inside the
| request even when the caller writes `->queue()`, so a slow relay is paid for
| by whoever clicked the button.
|
| QueueStalledMail is the one exception, and a deliberate one: it exists to
| report that the queue has stopped moving, which it could not do through the
| queue.
|
*/

/**
 * @return array<int, class-string<Mailable>>
 */
function mailableClasses(): array
{
    $files = Finder::create()
        ->files()
        ->in(dirname(__DIR__, 2) . '/app/Mail')
        ->name('*.php');

    $classes = [];

    foreach ($files as $file) {
        $class = 'App\\Mail\\' . $file->getBasename('.php');

        if (class_exists($class) && is_subclass_of($class, Mailable::class)) {
            $classes[] = $class;
        }
    }

    sort($classes);

    return $classes;
}

it('finds the mailables to check', function (): void {
    expect(mailableClasses())->not->toBeEmpty();
});

it('queues every mailable except the one reporting the queue is down', function (): void {
    $inline = array_values(array_filter(
        mailableClasses(),
        fn (string $class): bool => ! is_subclass_of($class, ShouldQueue::class),
    ));

    expect($inline)->toBe([QueueStalledMail::class]);
});
