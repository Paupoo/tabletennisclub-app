<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubChange;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Notifications\InterclubChangeNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubChangesHeldNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubOwnForfeitAlertNotification;
use App\Domains\Competitions\Interclub\Services\AfttCalendarImporter;
use App\Domains\Shared\Enums\InterclubChangeStatus;
use App\Domains\Shared\Enums\Role;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

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

/*
 * Six changes in one hour match nothing the federation ordinarily does: a
 * forfeit or a postponement touches one or two fixtures. A batch like that is
 * an error, or a correction that will be followed by its own — two waves of
 * contradicting mails to the whole club. It is written, held, and put in front
 * of a person.
 */
describe('when one run changes too much', function (): void {
    beforeEach(function (): void {
        $this->duty = User::factory()->withRole(Role::INTERCLUBS)->create();

        foreach (['PBBWH01/113', 'PBBWH02/115', 'PBBWH03/111', 'PBBWH04/114', 'PBBWH07/115', 'PBBWH08/115'] as $matchId) {
            afttPatchMatch($matchId, ['Time' => '21:00:00']);
        }
    });

    it('holds every message and alerts the interclubs duty once', function (): void {
        sync();

        Notification::assertNotSentTo([$this->rosterPlayer, $this->reinforcement, $this->captain], InterclubChangeNotification::class);
        Notification::assertSentToTimes($this->duty, InterclubChangesHeldNotification::class, 1);

        expect(InterclubChange::pluck('status')->unique()->all())->toBe([InterclubChangeStatus::HELD]);
    });

    it('lets the interclubs duty tell the teams once the changes are checked', function (): void {
        sync();

        $first = InterclubChange::orderBy('id')->first();
        $key = 'change:' . $first->id;

        Livewire::actingAs($this->duty)
            ->test('pages::club-events.interclubs.changes')
            ->assertSee(__('Match moved — :team vs :opponent', [
                'team' => $first->interclub->ourTeam()->fullName(),
                'opponent' => $first->interclub->opponentTeam()->fullName(),
            ]))
            ->set('selected', [$key])
            ->call('notifySelected');

        Notification::assertSentTo([$this->rosterPlayer, $this->reinforcement], InterclubChangeNotification::class);
        Notification::assertSentTo($this->captain, InterclubChangeNotification::class);

        expect($first->fresh()->status)->toBe(InterclubChangeStatus::SENT)
            ->and($first->fresh()->notified_by)->toBe($this->duty->id)
            ->and(InterclubChange::where('status', InterclubChangeStatus::HELD)->count())->toBe(5);
    });

    it('lets the interclubs duty let changes go untold', function (): void {
        sync();

        $keys = InterclubChange::pluck('id')->map(fn (int $id): string => 'change:' . $id)->all();

        Livewire::actingAs($this->duty)
            ->test('pages::club-events.interclubs.changes')
            ->set('selected', $keys)
            ->call('dismissSelected');

        Notification::assertNotSentTo([$this->rosterPlayer, $this->captain], InterclubChangeNotification::class);
        expect(InterclubChange::pluck('status')->unique()->all())->toBe([InterclubChangeStatus::DISMISSED]);
    });

    it('keeps the review screen to those who manage the interclubs', function (): void {
        $this->actingAs($this->rosterPlayer)
            ->get(route('admin.interclubs.changes'))
            ->assertForbidden();
    });

    it('counts a withdrawal once against the limit', function (): void {
        afttPatchMatch(reset: true);

        $withdrawn = ['Score' => '0-0 fg', 'IsHomeForfeited' => 'true', 'IsAwayForfeited' => 'true'];
        afttPatchMatch('PBBWH01/113', $withdrawn + ['IsAwayWithdrawn' => '1']);
        afttPatchMatch('PBBWH12/113', $withdrawn + ['IsHomeWithdrawn' => '1']);

        foreach (['PBBWH02/115', 'PBBWH03/111', 'PBBWH04/114', 'PBBWH07/115'] as $matchId) {
            afttPatchMatch($matchId, ['Time' => '21:00:00']);
        }

        sync();

        // Six fixtures, five messages: under the limit.
        Notification::assertNothingSentTo($this->duty);
        expect(InterclubChange::where('status', InterclubChangeStatus::HELD)->count())->toBe(0);
    });
});
