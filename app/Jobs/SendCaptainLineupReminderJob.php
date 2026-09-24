<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Notifications\CaptainLineupReminderNotification;
use App\Domains\Competitions\Interclub\Services\InterclubPreparationService;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Mails one captain the lineups still to send. The fixtures travel as ids and
 * are read back here, so a lineup sent in the meantime is left out.
 */
class SendCaptainLineupReminderJob implements ShouldQueue
{
    use Queueable, RetriesWhileRateLimited;

    /** @param array<int, int> $interclubIds */
    public function __construct(
        public int $captainId,
        public array $interclubIds,
    ) {}

    public function handle(): void
    {
        $captain = User::find($this->captainId);

        if ($captain === null) {
            return;
        }

        $fixtures = Interclub::with(['visitedTeam.club', 'visitingTeam.club', 'users'])
            ->whereIn('id', $this->interclubIds)
            ->orderBy('start_date_time')
            ->get()
            ->reject(fn (Interclub $ic): bool => in_array(app(InterclubPreparationService::class)->fixtureStatus($ic), InterclubPreparationService::SETTLED, true));

        if ($fixtures->isEmpty()) {
            return;
        }

        $captain->notify(new CaptainLineupReminderNotification($fixtures->values()));
    }

    /**
     * A broadcast shares the `invitations` limiter rather than adding a key.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('invitations')];
    }
}
