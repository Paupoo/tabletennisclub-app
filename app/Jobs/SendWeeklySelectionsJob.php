<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Competitions\Interclub\Notifications\WeeklySelectionsNotification;
use App\Domains\Competitions\Interclub\Services\WeeklySelections;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * One address's « Sélections de la semaine ». The window travels, the fixtures
 * do not: they are read again here, so a lineup sent while the queue drains
 * goes out with its players.
 *
 * Shares the `invitations` limiter of {@see AppServiceProvider}: a bulk,
 * member-facing mailing nobody waits on.
 */
class SendWeeklySelectionsJob implements ShouldQueue
{
    use Queueable, RetriesWhileRateLimited;

    /**
     * @param  array<int, int>  $memberIds  the members this address speaks for
     * @param  array<int, string>  $names  first names of whoever reads this address
     */
    public function __construct(
        public string $address,
        public array $memberIds,
        public string $from,
        public string $to,
        public array $names = [],
    ) {}

    public function handle(): void
    {
        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);
        $selections = app(WeeklySelections::class);
        $days = $selections->between($from, $to);

        if ($days->isEmpty()) {
            return;
        }

        Notification::route('mail', $this->address)
            ->notify(new WeeklySelectionsNotification($days, $this->memberIds, $from, $to, $this->names, $selections->byesBetween($from, $to)->all()));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('invitations')];
    }
}
