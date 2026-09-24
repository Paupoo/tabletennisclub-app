<?php

declare(strict_types=1);

namespace App\Console\Commands\Interclub;

use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Services\InterclubPreparationService;
use App\Jobs\SendCaptainLineupReminderJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The Sunday reminder: to each captain, the lineups of the next three weeks
 * that have not reached their team.
 *
 * One mail per captain rather than one per fixture, and none at all when
 * everything is sent — sending the lineups is the only way to stop it, which
 * is the point. Three weeks, so the fixture that crosses the two-week mark
 * during the week is named before it turns red.
 */
#[Signature('interclubs:remind-captains')]
#[Description('Remind each captain of the lineups of the next three weeks not sent to their team yet.')]
class RemindCaptainsCommand extends Command
{
    public const int HORIZON_DAYS = 21;

    public function handle(InterclubPreparationService $preparation): int
    {
        $pending = Interclub::query()
            ->with(['users', 'visitedTeam.club', 'visitingTeam.club'])
            ->withoutByes()
            ->where('start_date_time', '>', now())
            ->where('start_date_time', '<=', now()->addDays(self::HORIZON_DAYS))
            ->orderBy('start_date_time')
            ->orderBy('interclubs.id')
            ->get()
            ->reject(fn (Interclub $ic): bool => in_array($preparation->fixtureStatus($ic), InterclubPreparationService::SETTLED, true));

        $byCaptain = $pending
            ->groupBy(fn (Interclub $ic): string => (string) ($ic->ourTeam()?->captain_id ?? ''))
            ->reject(fn ($fixtures, string $captainId): bool => $captainId === '');

        foreach ($byCaptain as $captainId => $fixtures) {
            SendCaptainLineupReminderJob::dispatch((int) $captainId, $fixtures->pluck('id')->values()->all());
        }

        $this->info("Reminded {$byCaptain->count()} captain(s) of {$pending->count()} lineup(s) not sent.");

        return self::SUCCESS;
    }
}
