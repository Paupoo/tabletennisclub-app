<?php

declare(strict_types=1);

namespace App\Support\AutomaticEmails;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Cron\CronExpression;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * What the live schedule says about a scheduled email: how often, and when next.
 *
 * Read from routes/console.php, never copied: the page cannot announce a time
 * the scheduler has stopped keeping.
 */
final class ScheduledRuns
{
    /**
     * Two runs closer than this belong to a task that runs several times a
     * day. Not 24: a daily task is 23 hours from its previous run on the
     * morning the clocks go forward.
     */
    private const int CONTINUOUS_BELOW_HOURS = 20;

    /**
     * How many cron occurrences to try before giving up on a filter that
     * never lets one through — a year of a daily task, and then some.
     */
    private const int SEARCH_LIMIT = 400;

    /** @var array<string, array<int, Event>>|null */
    private ?array $eventsByCommand = null;

    public function __construct(private readonly Application $app) {}

    /**
     * The command a task runs, arguments included, without the PHP binary
     * and the artisan path in front: « expense-reports:remind-archiving --year-end ».
     */
    public static function commandOf(Event $event): ?string
    {
        if ($event->command === null) {
            return null;
        }

        $prefix = trim(Artisan::formatCommandString(''));

        return Str::startsWith($event->command, $prefix)
            ? trim(Str::after($event->command, $prefix))
            : null;
    }

    /**
     * The cadence in words: the catalogue's, when the cron alone would
     * mislead, else read from each cron expression.
     */
    public function cadence(AutomaticEmail $email): string
    {
        if ($email->cadence !== null) {
            return $email->cadence;
        }

        return collect($this->eventsFor($email))
            ->map(fn (Event $event): string => $this->describe($event->expression))
            ->unique()
            ->implode(' · ');
    }

    /**
     * The schedule, indexed by command.
     *
     * routes/console.php is read when the console kernel boots, which a web
     * request never does: there the schedule is empty. Booting the kernel
     * here is what makes the page show in production what the tests see.
     *
     * @return array<string, array<int, Event>>
     */
    public function eventsByCommand(): array
    {
        if ($this->eventsByCommand !== null) {
            return $this->eventsByCommand;
        }

        $this->app->make(ConsoleKernel::class)->bootstrap();

        $byCommand = [];

        foreach ($this->app->make(Schedule::class)->events() as $event) {
            $command = self::commandOf($event);

            if ($command !== null) {
                $byCommand[$command][] = $event;
            }
        }

        return $this->eventsByCommand = $byCommand;
    }

    /**
     * The scheduled tasks that send this email, as the schedule holds them.
     *
     * @return array<int, Event>
     */
    public function eventsFor(AutomaticEmail $email): array
    {
        $byCommand = $this->eventsByCommand();

        return collect($email->commands)
            ->flatMap(fn (string $command): array => $byCommand[$command] ?? [])
            ->values()
            ->all();
    }

    /**
     * Whether one of its tasks runs more than once a day — its next run then
     * says nothing: the email leaves when a deadline falls, not on the hour.
     */
    public function isContinuous(AutomaticEmail $email, ?CarbonInterface $from = null): bool
    {
        $from ??= now();

        foreach ($this->eventsFor($email) as $event) {
            $cron = new CronExpression($event->expression);
            $timezone = $this->timezone($event);
            $first = CarbonImmutable::instance($cron->getNextRunDate($from, 0, false, $timezone));
            $second = CarbonImmutable::instance($cron->getNextRunDate($from, 1, false, $timezone));

            if ($first->diffInHours($second) < self::CONTINUOUS_BELOW_HOURS) {
                return true;
            }
        }

        return false;
    }

    /**
     * The next time a task actually sends: the first cron occurrence its
     * filters let through — a domain switched off, or a monthly task kept to
     * the quarters, would otherwise show a date it will skip.
     *
     * Laravel's own nextRunDate() reads the cron only. Here the clock is
     * moved to each occurrence in turn and the filters asked, as the
     * scheduler would ask them then.
     */
    public function nextRun(AutomaticEmail $email, ?CarbonInterface $from = null): ?CarbonImmutable
    {
        $from ??= now();

        return collect($this->eventsFor($email))
            ->map(fn (Event $event): ?CarbonImmutable => $this->nextPassingRun($event, $from))
            ->filter()
            ->sort()
            ->first();
    }

    private function describe(string $expression): string
    {
        $fields = preg_split('/\s+/', trim($expression));

        if (! is_array($fields) || count($fields) !== 5) {
            return $expression;
        }

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $fields;

        if (! ctype_digit($minute)) {
            return $expression;
        }

        if ($hour === '*') {
            return __('Every hour');
        }

        if (preg_match('/^(\d+)-(\d+)$/', $hour, $range) === 1) {
            return __('Every hour from :from to :to', [
                'from' => $this->time((int) $range[1], (int) $minute),
                'to' => $this->time((int) $range[2], (int) $minute),
            ]);
        }

        if (! ctype_digit($hour)) {
            return $expression;
        }

        $time = $this->time((int) $hour, (int) $minute);
        $locale = $this->app->getLocale();

        return match (true) {
            $dayOfMonth === '*' && $month === '*' && $dayOfWeek === '*' => __('Every day at :time', ['time' => $time]),
            $dayOfMonth === '*' && $month === '*' && ctype_digit($dayOfWeek) => __('Every :day at :time', [
                'day' => CarbonImmutable::now()->locale($locale)->startOfWeek(CarbonInterface::SUNDAY)->addDays((int) $dayOfWeek % 7)->dayName,
                'time' => $time,
            ]),
            ctype_digit($dayOfMonth) && $month === '*' && $dayOfWeek === '*' => __('On day :day of every month at :time', [
                'day' => $dayOfMonth,
                'time' => $time,
            ]),
            ctype_digit($dayOfMonth) && ctype_digit($month) && $dayOfWeek === '*' => __('Every year on :date at :time', [
                'date' => CarbonImmutable::create(2000, (int) $month, (int) $dayOfMonth)?->locale($locale)->translatedFormat('j F'),
                'time' => $time,
            ]),
            default => $expression,
        };
    }

    private function nextPassingRun(Event $event, CarbonInterface $from): ?CarbonImmutable
    {
        $cron = new CronExpression($event->expression);
        $timezone = $this->timezone($event);
        $frozen = Carbon::getTestNow();
        $cursor = CarbonImmutable::instance($from);

        try {
            for ($attempt = 0; $attempt < self::SEARCH_LIMIT; $attempt++) {
                $run = CarbonImmutable::instance($cron->getNextRunDate($cursor, 0, false, $timezone));

                Carbon::setTestNow($run);

                if ($event->filtersPass($this->app)) {
                    return $run;
                }

                $cursor = $run;
            }
        } finally {
            Carbon::setTestNow($frozen);
        }

        return null;
    }

    private function time(int $hour, int $minute): string
    {
        return sprintf('%02dh%02d', $hour, $minute);
    }

    private function timezone(Event $event): string
    {
        $timezone = $event->timezone;

        return $timezone instanceof \DateTimeZone ? $timezone->getName() : (string) $timezone;
    }
}
