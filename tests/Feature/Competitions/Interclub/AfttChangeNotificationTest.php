<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubChange;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Notifications\InterclubChangeNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubOwnForfeitAlertNotification;
use App\Domains\Competitions\Interclub\Services\AfttCalendarImporter;
use App\Domains\Shared\Enums\InterclubChangeStatus;
use App\Domains\Shared\Enums\Role;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/*
 * The team hears what the federation changed from the application, not from
 * whoever happens to forward the federation's mail. Same audience as the
 * lineup mail: the players selected, reinforcements included, and the whole
 * team roster. The captain gets a message of their own instead, saying the
 * team has been told.
 */

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-01 10:00:00');
    CarbonImmutable::setTestNow('2026-09-01 10:00:00');

    afttClubTeams('get-club-teams-bbw214-two-divisions.xml');
    afttFaultOn(reset: true);
    fakeTabt();
    knownOpponents();

    $this->season = Season::factory()->create(['name' => '2026-2027']);
    $this->ownClub = Club::factory()->create(['is_own_club' => true, 'licence' => 'BBW214']);

    app(AfttCalendarImporter::class)->import($this->season, 27, 'BBW214');

    // Our E, at home against Hamme Mille D on 2026-09-18.
    $this->fixture = Interclub::where('aftt_match_id', 'PBBWH01/113')->first();
    $this->team = $this->fixture->ourTeam();

    $this->captain = User::factory()->isCompetitor()->create();
    $this->rosterPlayer = User::factory()->isCompetitor()->create();
    $this->reinforcement = User::factory()->isCompetitor()->create();
    $this->stranger = User::factory()->isCompetitor()->create();

    $this->team->update(['captain_id' => $this->captain->id]);
    $this->team->users()->attach([$this->captain->id, $this->rosterPlayer->id]);
    $this->fixture->users()->attach($this->reinforcement->id, ['is_selected' => true]);

    Notification::fake();
});

afterEach(function (): void {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

function sync(array $options = []): void
{
    test()->artisan('interclubs:import-aftt', $options)->assertSuccessful();
}

it('tells the team and the reinforcements of an opponent forfeit, and the captain on their own', function (): void {
    afttPatchMatch('PBBWH01/113', ['Score' => '16-0 ff', 'IsAwayForfeited' => 'true', 'IsLocked' => 'true']);

    sync();

    Notification::assertSentTo(
        [$this->rosterPlayer, $this->reinforcement],
        InterclubChangeNotification::class,
        fn (InterclubChangeNotification $notification): bool => ! $notification->forCaptain,
    );
    Notification::assertSentTo(
        $this->captain,
        InterclubChangeNotification::class,
        fn (InterclubChangeNotification $notification): bool => $notification->forCaptain
            && $notification->informedCount === 2,
    );
    Notification::assertSentToTimes($this->captain, InterclubChangeNotification::class, 1);
    Notification::assertNotSentTo($this->stranger, InterclubChangeNotification::class);
});

it('tells the team a new moment as what it was and what it is now, and sends the captain to the front', function (): void {
    afttPatchMatch('PBBWH01/113', ['Date' => '2026-09-22', 'Time' => '20:00:00']);

    sync();

    Notification::assertSentTo($this->rosterPlayer, InterclubChangeNotification::class, function (InterclubChangeNotification $notification): bool {
        $html = (string) $notification->toMail($this->rosterPlayer)->render();

        return str_contains($html, '18 septembre 2026')
            && str_contains($html, '22 septembre 2026')
            && str_contains($html, '20:00')
            && str_contains($html, e(__('Contact your captain as soon as possible if you have a question or a problem.')))
            && str_contains($html, e($this->captain->full_name));
    });

    Notification::assertSentTo($this->captain, InterclubChangeNotification::class, function (InterclubChangeNotification $notification): bool {
        $html = (string) $notification->toMail($this->captain)->render();

        return str_contains($html, '22 septembre 2026')
            && ! str_contains($html, e(__('Contact your captain as soon as possible if you have a question or a problem.')));
    });
});

it('tells the team once about a withdrawal, with every fixture it cancels', function (): void {
    $withdrawn = ['Score' => '0-0 fg', 'IsHomeForfeited' => 'true', 'IsAwayForfeited' => 'true'];
    afttPatchMatch('PBBWH01/113', $withdrawn + ['IsAwayWithdrawn' => '1']);
    afttPatchMatch('PBBWH12/113', $withdrawn + ['IsHomeWithdrawn' => '1']);

    sync();

    Notification::assertSentToTimes($this->rosterPlayer, InterclubChangeNotification::class, 1);
    Notification::assertSentTo($this->rosterPlayer, InterclubChangeNotification::class, fn (InterclubChangeNotification $notification): bool => $notification->changes->count() === 2);
});

it('alerts the interclubs duty when the federation records a forfeit of ours', function (): void {
    $duty = User::factory()->withRole(Role::INTERCLUBS)->create();
    afttPatchMatch('PBBWH01/113', ['Score' => '0-16 ff', 'IsHomeForfeited' => 'true', 'IsLocked' => 'true']);

    sync();

    Notification::assertSentTo($duty, InterclubOwnForfeitAlertNotification::class);
    Notification::assertSentTo($this->rosterPlayer, InterclubChangeNotification::class);
});

it('says nothing to the interclubs duty about an opponent forfeit', function (): void {
    $duty = User::factory()->withRole(Role::INTERCLUBS)->create();
    afttPatchMatch('PBBWH01/113', ['Score' => '16-0 ff', 'IsAwayForfeited' => 'true']);

    sync();

    Notification::assertNotSentTo($duty, InterclubOwnForfeitAlertNotification::class);
});

it('marks what it told the team as sent, and never tells it twice', function (): void {
    afttPatchMatch('PBBWH01/113', ['Score' => '16-0 ff', 'IsAwayForfeited' => 'true']);

    sync();
    sync();

    expect(InterclubChange::sole()->status)->toBe(InterclubChangeStatus::SENT);
    Notification::assertSentToTimes($this->rosterPlayer, InterclubChangeNotification::class, 1);
});

it('tells nobody when the run is silent', function (): void {
    afttPatchMatch('PBBWH01/113', ['Score' => '16-0 ff', 'IsAwayForfeited' => 'true']);

    sync(['--silent' => true]);

    Notification::assertNothingSent();
});
