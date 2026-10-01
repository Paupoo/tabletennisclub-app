<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubChange;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Services\AfttCalendarImporter;
use App\Domains\Shared\Enums\InterclubChangeKind;
use App\Domains\Shared\Enums\InterclubChangeStatus;
use App\Domains\Shared\Enums\InterclubForfeit;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

/*
 * What the hourly sync noticed, kept with what it was before. A member is told
 * "from X to Y"; once the row is written, nothing else remembers X.
 *
 * The committed fixtures are a September snapshot, so the clock is set to the
 * start of the season: every fixture is still to come.
 */

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-01 10:00:00');
    CarbonImmutable::setTestNow('2026-09-01 10:00:00');

    afttClubTeams('get-club-teams-bbw214-two-divisions.xml');
    afttFaultOn(reset: true);
    fakeTabt();
    knownOpponents();

    $this->season = Season::factory()->create(['name' => '2026-2027']);
    Club::factory()->create(['is_own_club' => true, 'licence' => 'BBW214']);

    // The season as it stood before the federation changed its mind.
    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');
});

afterEach(function (): void {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

function resync(): void
{
    app(AfttCalendarImporter::class)->import(test()->season, 27, 'BBW214');
}

it('keeps a rescheduled fixture with its old and its new moment and hall', function (): void {
    afttPatchMatch('PBBWH01/113', [
        'Date' => '2026-09-22',
        'Time' => '20:00:00',
    ]);

    resync();

    $change = InterclubChange::sole();
    $fixture = Interclub::where('aftt_match_id', 'PBBWH01/113')->first();

    expect($change->interclub_id)->toBe($fixture->id)
        ->and($change->kind)->toBe(InterclubChangeKind::RESCHEDULED)
        ->and($change->status)->toBe(InterclubChangeStatus::PENDING)
        ->and($change->before['start'])->toBe('2026-09-18 19:45:00')
        ->and($change->after['start'])->toBe('2026-09-22 20:00:00')
        ->and($change->before['address'])->toBe($change->after['address']);
});

it('notes nothing when the federation has changed nothing', function (): void {
    resync();

    expect(InterclubChange::count())->toBe(0);
});

it('keeps a hall change on the same evening', function (): void {
    afttPatchMatch('PBBWH01/113', ['Name' => 'HALL OMNISPORTS DE LIMELETTE']);

    resync();

    $change = InterclubChange::sole();

    expect($change->kind)->toBe(InterclubChangeKind::RESCHEDULED)
        ->and($change->after['address'])->toStartWith('Hall Omnisports de Limelette')
        ->and($change->before['start'])->toBe($change->after['start']);
});

it('keeps a forfeit, and its retraction', function (): void {
    afttPatchMatch('PBBWH01/113', ['Score' => '16-0 ff', 'IsAwayForfeited' => 'true', 'IsLocked' => 'true']);
    resync();

    afttPatchMatch(reset: true);
    resync();

    $changes = InterclubChange::orderBy('id')->get();

    expect($changes->pluck('kind')->all())->toBe([InterclubChangeKind::FORFEIT, InterclubChangeKind::FORFEIT_LIFTED])
        ->and($changes->pluck('forfeit')->all())->toBe([InterclubForfeit::OPPONENT_FORFEIT, InterclubForfeit::OPPONENT_FORFEIT]);
});

it('does not announce a new time for an evening cancelled by forfeit', function (): void {
    afttPatchMatch('PBBWH01/113', ['Score' => '16-0 ff', 'IsAwayForfeited' => 'true']);
    resync();

    afttPatchMatch('PBBWH01/113', ['Date' => '2026-09-22']);
    resync();

    expect(InterclubChange::pluck('kind')->all())->toBe([InterclubChangeKind::FORFEIT]);
});

it('folds the fixtures one withdrawal cancels into one group', function (): void {
    // Hamme Mille D, our E's opponent home and away.
    $withdrawn = ['Score' => '0-0 fg', 'IsHomeForfeited' => 'true', 'IsAwayForfeited' => 'true'];
    afttPatchMatch('PBBWH01/113', $withdrawn + ['IsAwayWithdrawn' => '1']);
    afttPatchMatch('PBBWH12/113', $withdrawn + ['IsHomeWithdrawn' => '1']);

    resync();

    $changes = InterclubChange::all();

    expect($changes)->toHaveCount(2)
        ->and($changes->pluck('forfeit')->unique()->all())->toBe([InterclubForfeit::OPPONENT_WITHDRAWAL])
        ->and($changes->pluck('group_key')->unique())->toHaveCount(1)
        ->and($changes->first()->group_key)->not->toBeNull();
});

it('records a change to a fixture already under way without announcing it', function (): void {
    Carbon::setTestNow('2026-09-18 20:00:00');
    CarbonImmutable::setTestNow('2026-09-18 20:00:00');

    afttPatchMatch('PBBWH01/113', ['Score' => '16-0 ff', 'IsAwayForfeited' => 'true']);
    resync();

    expect(InterclubChange::sole()->status)->toBe(InterclubChangeStatus::SILENT);
});

it('records without announcing anything when the run is told to stay silent', function (): void {
    afttPatchMatch('PBBWH01/113', ['Date' => '2026-09-22']);

    $this->artisan('interclubs:import-aftt', ['--silent' => true])->assertSuccessful();

    expect(InterclubChange::sole()->status)->toBe(InterclubChangeStatus::SILENT);
});

it('runs every hour from 7:00 to 22:00, only where interclubs are enabled', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'interclubs:import-aftt'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('5 7-22 * * *');
});
