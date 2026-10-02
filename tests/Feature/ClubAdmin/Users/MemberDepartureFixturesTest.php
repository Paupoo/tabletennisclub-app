<?php

declare(strict_types=1);

use App\Actions\User\DeclareMemberDepartureAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Data\MemberDepartureOutcome;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Notifications\MemberLeftLineupNotification;
use App\Domains\Shared\Enums\DepartureReason;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\Shared\Enums\Role;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

pest()->group('club-admin', 'users');

/*
| A member who leaves is taken off the interclub matches still to come: their
| availability, their place in a draft, their place in a lineup already sent.
| A draft or an availability goes quietly. A lineup the team has received is
| another matter: the captain must find somebody else, so the captain and the
| interclubs manager are told — one mail each, whatever the number of matches.
| The matches already played are never touched.
*/

beforeEach(function (): void {
    Notification::fake();

    $this->season = makeActiveSeason();
    $this->office = User::factory()->isAdmin()->create();
    $this->manager = User::factory()->withRole(Role::INTERCLUBS)->create();

    $ownClub = Club::factory()->ownClub()->create();
    $otherClub = Club::factory()->create();
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    $this->captain = User::factory()->isCompetitor()->create();
    $this->member = User::factory()->isCompetitor()->create(['first_name' => 'Jeanne', 'last_name' => 'Depart-Interclub']);
    $this->stays = User::factory()->isCompetitor()->create();

    foreach ([$this->member, $this->stays, $this->captain] as $player) {
        Subscription::factory()->for($player)->for($this->season)->create(['status' => 'paid', 'is_competitive' => true]);
    }

    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $ownClub->id,
        'name' => 'C',
        'captain_id' => $this->captain->id,
    ]);
    $this->team->users()->attach([$this->member->id, $this->stays->id, $this->captain->id]);

    $this->opponent = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $otherClub->id,
        'name' => 'B',
        'captain_id' => null,
    ]);

    $this->declare = fn (User $member, ?CarbonInterface $leftOn = null): MemberDepartureOutcome => DeclareMemberDepartureAction::handle(
        $member,
        $leftOn ?? now(),
        DepartureReason::Moving,
        null,
        $this->office,
    );
});

/**
 * A fixture of team C at home, the given number of days from now.
 */
function departureFixture(Team $team, Team $opponent, int $days): Interclub
{
    return Interclub::factory()->create([
        'season_id' => $team->season_id,
        'league_id' => $team->league_id,
        'visited_team_id' => $team->id,
        'visiting_team_id' => $opponent->id,
        'total_players' => 4,
        'is_bye' => false,
        'start_date_time' => now()->addDays($days),
    ]);
}

/**
 * Put the given players in the lineup of the fixture, sent to the team or not.
 *
 * @param  array<int, User>  $players
 */
function departureLineup(Interclub $fixture, array $players, bool $published): void
{
    foreach ($players as $player) {
        $fixture->users()->attach($player->id, [
            'availability' => 'available',
            'is_selected' => true,
            'selection_confirmed_at' => $published ? now()->subDay() : null,
        ]);
    }
}

/**
 * The ids of the members still on the fixture, in any role.
 *
 * @return array<int, int>
 */
function departureFixturePlayers(Interclub $fixture): array
{
    return $fixture->users()->orderBy('users.id')->pluck('users.id')->all();
}

/**
 * The title of the Mary toast the last call pushed.
 */
function departureFixturesToastTitle(object $component): string
{
    foreach ($component->effects['xjs'] ?? [] as $effect) {
        if (preg_match('/^toast\((.*)\)$/s', (string) ($effect['expression'] ?? ''), $matches) === 1) {
            return json_decode($matches[1], true)['toast']['title'] ?? '';
        }
    }

    return '';
}

describe('the matches still to come', function (): void {
    it('takes the member off them, and leaves the other players where they are', function (): void {
        $fixture = departureFixture($this->team, $this->opponent, 10);
        $fixture->markAvailability($this->member, InterclubAvailability::AVAILABLE);
        $fixture->markAvailability($this->stays, InterclubAvailability::AVAILABLE);

        $outcome = ($this->declare)($this->member);

        expect(departureFixturePlayers($fixture))->toBe([$this->stays->id])
            ->and($outcome->fixturesLeft)->toBe(1)
            ->and($outcome->lineupsLeft)->toBe(0);
    });

    it('leaves the matches already played as they were', function (): void {
        $played = departureFixture($this->team, $this->opponent, -5);
        departureLineup($played, [$this->member, $this->stays], published: true);
        $played->users()->updateExistingPivot($this->member->id, ['has_played' => true]);

        $outcome = ($this->declare)($this->member, now()->subDays(10));

        expect(departureFixturePlayers($played))->toBe([$this->member->id, $this->stays->id])
            ->and($outcome->fixturesLeft)->toBe(0);

        Notification::assertNothingSent();
    });

    it('keeps the matches before a departure announced for later', function (): void {
        $before = departureFixture($this->team, $this->opponent, 10);
        $after = departureFixture($this->team, $this->opponent, 30);
        departureLineup($before, [$this->member, $this->stays], published: false);
        departureLineup($after, [$this->member, $this->stays], published: false);

        ($this->declare)($this->member, now()->addDays(20));

        expect(departureFixturePlayers($before))->toBe([$this->member->id, $this->stays->id])
            ->and(departureFixturePlayers($after))->toBe([$this->stays->id]);
    });

    it('takes the member off a draft without telling anybody', function (): void {
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureLineup($fixture, [$this->member, $this->stays], published: false);

        $outcome = ($this->declare)($this->member);

        expect(departureFixturePlayers($fixture))->toBe([$this->stays->id])
            ->and($outcome->lineupsLeft)->toBe(0)
            ->and($outcome->captainTold)->toBeFalse();

        Notification::assertNothingSent();
    });
});

describe('a lineup the team has already received', function (): void {
    it('tells the captain and the interclubs manager, and nobody else', function (): void {
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureLineup($fixture, [$this->member, $this->stays], published: true);

        $outcome = ($this->declare)($this->member);

        expect(departureFixturePlayers($fixture))->toBe([$this->stays->id])
            ->and($outcome->lineupsLeft)->toBe(1)
            ->and($outcome->captainTold)->toBeTrue();

        Notification::assertSentTo($this->captain, MemberLeftLineupNotification::class, fn (MemberLeftLineupNotification $notification): bool => $notification->interclubIds === [$fixture->id]
            && $notification->memberName === 'Jeanne Depart-Interclub');
        Notification::assertSentTo($this->manager, MemberLeftLineupNotification::class);
        Notification::assertNotSentTo([$this->stays, $this->member, $this->office], MemberLeftLineupNotification::class);
        Notification::assertCount(2);
    });

    it('sends one mail per person for all the matches concerned', function (): void {
        $first = departureFixture($this->team, $this->opponent, 10);
        $second = departureFixture($this->team, $this->opponent, 24);
        departureLineup($first, [$this->member, $this->stays], published: true);
        departureLineup($second, [$this->member, $this->stays], published: true);

        ($this->declare)($this->member);

        Notification::assertSentToTimes($this->captain, MemberLeftLineupNotification::class, 1);
        Notification::assertSentToTimes($this->manager, MemberLeftLineupNotification::class, 1);
        Notification::assertSentTo($this->captain, MemberLeftLineupNotification::class, fn (MemberLeftLineupNotification $notification): bool => $notification->interclubIds === [$first->id, $second->id]);
    });

    it('sends a captain who is also the interclubs manager a single mail', function (): void {
        $this->captain->assignRole(Role::INTERCLUBS->value);
        $this->manager->removeRole(Role::INTERCLUBS->value);
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureLineup($fixture, [$this->member, $this->stays], published: true);

        ($this->declare)($this->member);

        Notification::assertSentToTimes($this->captain, MemberLeftLineupNotification::class, 1);
        Notification::assertCount(1);
    });

    it('still tells the interclubs manager when the member was the captain', function (): void {
        $this->team->update(['captain_id' => $this->member->id]);
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureLineup($fixture, [$this->member, $this->stays], published: true);

        $outcome = ($this->declare)($this->member);

        expect($outcome->captainTold)->toBeFalse()
            ->and($outcome->lineupsLeft)->toBe(1);

        Notification::assertSentTo($this->manager, MemberLeftLineupNotification::class);
        Notification::assertNotSentTo($this->member, MemberLeftLineupNotification::class);
    });

    it('leaves the lineup published, and the stamps of the others, as they were', function (): void {
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureLineup($fixture, [$this->member, $this->stays], published: true);

        ($this->declare)($this->member);

        expect($fixture->isLineupPublished())->toBeTrue()
            ->and($fixture->users()->sole()->registration->selection_confirmed_at)->not->toBeNull();
    });

    it('names the member, the date and the opponent in the mail', function (): void {
        $fixture = departureFixture($this->team, $this->opponent, 10);

        $mail = new MemberLeftLineupNotification('Jeanne Depart-Interclub', [$fixture->id])->toMail($this->captain);
        $text = implode(' ', $mail->introLines);

        expect($text)->toContain('Jeanne Depart-Interclub')
            ->toContain($fixture->start_date_time->format('d/m/Y'))
            ->toContain($this->opponent->fullName())
            ->and($mail->actionUrl)->toBe(route('admin.interclubs.captain-selection'))
            ->and((string) $mail->render())->toContain('Depart-Interclub');
    });
});

describe('the screens', function (): void {
    it('says on the member file how many matches were freed and who was told', function (): void {
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureLineup($fixture, [$this->member, $this->stays], published: true);
        $draft = departureFixture($this->team, $this->opponent, 24);
        departureLineup($draft, [$this->member], published: false);

        $component = Livewire::actingAs($this->office)
            ->test('pages::club-admin.users.show', ['user' => $this->member])
            ->set('departureReason', DepartureReason::Moving->value)
            ->set('departureLeftOn', now()->toDateString())
            ->call('declareDeparture')
            ->assertHasNoErrors();

        expect(departureFixturesToastTitle($component))
            ->toContain(trans_choice('{1} Place freed in :count upcoming interclub match.|[2,*] Places freed in :count upcoming interclub matches.', 2, ['count' => 2]))
            ->toContain(__('Captain and interclubs manager told.'));
    });

    it('takes a whole selection off their matches, one mail per departure', function (): void {
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureLineup($fixture, [$this->member, $this->stays], published: true);
        $other = departureFixture($this->team, $this->opponent, 24);
        departureLineup($other, [$this->stays], published: false);

        $component = Livewire::actingAs($this->office)
            ->test('pages::club-admin.users.index')
            ->set('selected', [(string) $this->member->id, (string) $this->stays->id])
            ->set('departureReason', DepartureReason::NoResponse->value)
            ->set('departureLeftOn', now()->toDateString())
            ->call('bulkDeclareDeparture')
            ->assertHasNoErrors();

        expect(departureFixturePlayers($fixture))->toBe([])
            ->and(departureFixturePlayers($other))->toBe([])
            ->and(departureFixturesToastTitle($component))
            ->toContain(trans_choice('{1} Place freed in :count upcoming interclub match.|[2,*] Places freed in :count upcoming interclub matches.', 3, ['count' => 3]))
            ->toContain(__('Captain and interclubs manager told.'));

        Notification::assertSentToTimes($this->captain, MemberLeftLineupNotification::class, 2);
        Notification::assertSentToTimes($this->manager, MemberLeftLineupNotification::class, 2);
    });

    it('says only the interclubs manager was told when the team has no captain', function (): void {
        $this->team->update(['captain_id' => null]);
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureLineup($fixture, [$this->member], published: true);

        $component = Livewire::actingAs($this->office)
            ->test('pages::club-admin.users.show', ['user' => $this->member])
            ->set('departureReason', DepartureReason::Moving->value)
            ->set('departureLeftOn', now()->toDateString())
            ->call('declareDeparture');

        expect(departureFixturesToastTitle($component))
            ->toContain(__('Interclubs manager told.'))
            ->not->toContain(__('Captain and interclubs manager told.'));
    });
});
