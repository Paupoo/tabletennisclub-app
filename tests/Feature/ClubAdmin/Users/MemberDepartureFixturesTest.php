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
use App\Domains\Competitions\Interclub\Notifications\MemberLeftTeamNotification;
use App\Domains\Competitions\Interclub\Services\InterclubPreparationService;
use App\Domains\Shared\Enums\DepartureReason;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\Shared\Enums\Role;
use Carbon\CarbonInterface;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

pest()->group('club-admin', 'users');

/*
| A member who leaves is taken off the interclub matches still to come: their
| availability, their place in a draft, their place in a lineup already sent.
| The matches already played are never touched.
|
| Whoever captains a team the member played in is told, and nobody else: one
| mail per captain and per departure, whether the member was in a lineup sent,
| a draft, or no match at all. The interclubs manager is not mailed.
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
            ->and($outcome->fixturesLeft)->toBe(1);
    });

    it('leaves the matches already played as they were', function (): void {
        $played = departureFixture($this->team, $this->opponent, -5);
        departureLineup($played, [$this->member, $this->stays], published: true);
        $played->users()->updateExistingPivot($this->member->id, ['has_played' => true]);

        $outcome = ($this->declare)($this->member, now()->subDays(10));

        expect(departureFixturePlayers($played))->toBe([$this->member->id, $this->stays->id])
            ->and($outcome->fixturesLeft)->toBe(0);

        Notification::assertSentTo($this->captain, MemberLeftTeamNotification::class, fn (MemberLeftTeamNotification $notification): bool => $notification->interclubIds === []);
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

    it('takes the member off a draft, and names it to the captain alone', function (): void {
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureLineup($fixture, [$this->member, $this->stays], published: false);

        $outcome = ($this->declare)($this->member);

        expect(departureFixturePlayers($fixture))->toBe([$this->stays->id])
            ->and($outcome->captainsTold)->toBe([$this->captain->id]);

        Notification::assertSentTo($this->captain, MemberLeftTeamNotification::class, fn (MemberLeftTeamNotification $notification): bool => $notification->interclubIds === [$fixture->id]
            && $notification->teamNames === ['C']);
        Notification::assertNotSentTo($this->manager, MemberLeftTeamNotification::class);
        Notification::assertCount(1);
    });

    it('does not list a match the member was only available for', function (): void {
        $fixture = departureFixture($this->team, $this->opponent, 10);
        $fixture->markAvailability($this->member, InterclubAvailability::AVAILABLE);

        ($this->declare)($this->member);

        expect(departureFixturePlayers($fixture))->toBe([]);

        Notification::assertSentTo($this->captain, MemberLeftTeamNotification::class, fn (MemberLeftTeamNotification $notification): bool => $notification->interclubIds === []);
    });
});

describe('a lineup the team has already received', function (): void {
    it('tells the captain, and not the interclubs manager', function (): void {
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureLineup($fixture, [$this->member, $this->stays], published: true);

        $outcome = ($this->declare)($this->member);

        expect(departureFixturePlayers($fixture))->toBe([$this->stays->id])
            ->and($outcome->captainsTold)->toBe([$this->captain->id]);

        Notification::assertSentTo($this->captain, MemberLeftTeamNotification::class, fn (MemberLeftTeamNotification $notification): bool => $notification->interclubIds === [$fixture->id]
            && $notification->memberName === 'Jeanne Depart-Interclub');
        Notification::assertNotSentTo([$this->manager, $this->stays, $this->member, $this->office], MemberLeftTeamNotification::class);
        Notification::assertCount(1);
    });

    it('sends the captain one mail for all the matches concerned', function (): void {
        $first = departureFixture($this->team, $this->opponent, 10);
        $second = departureFixture($this->team, $this->opponent, 24);
        departureLineup($first, [$this->member, $this->stays], published: true);
        departureLineup($second, [$this->member, $this->stays], published: true);

        ($this->declare)($this->member);

        Notification::assertSentToTimes($this->captain, MemberLeftTeamNotification::class, 1);
        Notification::assertSentTo($this->captain, MemberLeftTeamNotification::class, fn (MemberLeftTeamNotification $notification): bool => $notification->interclubIds === [$first->id, $second->id]);
        Notification::assertCount(1);
    });

    it('sends a captain who is also the interclubs manager a single mail', function (): void {
        $this->captain->assignRole(Role::INTERCLUBS->value);
        $this->manager->removeRole(Role::INTERCLUBS->value);
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureLineup($fixture, [$this->member, $this->stays], published: true);

        ($this->declare)($this->member);

        Notification::assertSentToTimes($this->captain, MemberLeftTeamNotification::class, 1);
        Notification::assertCount(1);
    });

    it('tells nobody when the member was the captain', function (): void {
        $this->team->update(['captain_id' => $this->member->id]);
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureLineup($fixture, [$this->member, $this->stays], published: true);

        $outcome = ($this->declare)($this->member);

        expect($outcome->captainsTold)->toBe([])
            ->and($outcome->teamsWithoutCaptain)->toBe(['C']);

        Notification::assertNothingSent();
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

        $mail = new MemberLeftTeamNotification('Jeanne Depart-Interclub', $this->season->id, ['C'], [$fixture->id])->toMail($this->captain);
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
            ->toContain(trans_choice('{1} Captain told.|[2,*] Captains told.', 1));
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
            ->toContain(trans_choice('{1} Captain told.|[2,*] Captains told.', 1));

        Notification::assertSentToTimes($this->captain, MemberLeftTeamNotification::class, 2);
        Notification::assertNotSentTo($this->manager, MemberLeftTeamNotification::class);
    });

    it('says nobody was told when the team has no captain', function (): void {
        $this->team->update(['captain_id' => null]);
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureLineup($fixture, [$this->member], published: true);

        $component = Livewire::actingAs($this->office)
            ->test('pages::club-admin.users.show', ['user' => $this->member])
            ->set('departureReason', DepartureReason::Moving->value)
            ->set('departureLeftOn', now()->toDateString())
            ->call('declareDeparture');

        expect(departureFixturesToastTitle($component))
            ->toContain(trans_choice('{1} Place freed in :count upcoming interclub match.|[2,*] Places freed in :count upcoming interclub matches.', 1, ['count' => 1]))
            ->not->toContain(trans_choice('{1} Captain told.|[2,*] Captains told.', 1));

        Notification::assertNothingSent();
    });

    it('says the captain was told when the member was on no match to come', function (): void {
        $component = Livewire::actingAs($this->office)
            ->test('pages::club-admin.users.show', ['user' => $this->member])
            ->set('departureReason', DepartureReason::Moving->value)
            ->set('departureLeftOn', now()->toDateString())
            ->call('declareDeparture');

        expect(departureFixturesToastTitle($component))
            ->toContain(trans_choice('{1} Captain told.|[2,*] Captains told.', 1))
            ->not->toContain(trans_choice('{1} Place freed in :count upcoming interclub match.|[2,*] Places freed in :count upcoming interclub matches.', 1, ['count' => 1]));
    });

    it('warns, when a departure is cancelled, that the matches are not given back', function (): void {
        ($this->declare)($this->member);

        $component = Livewire::actingAs($this->office)
            ->test('pages::club-admin.users.show', ['user' => $this->member])
            ->set('cancelDepartureModal', true)
            ->assertSee(__('Use it for a departure recorded by mistake. Team places, captaincies and places in upcoming interclub matches are not given back.'))
            ->call('cancelDeparture');

        expect(departureFixturesToastTitle($component))
            ->toBe(__('Departure cancelled. Team places, captaincies and places in upcoming interclub matches are not given back: assign them again if needed.'));
    });
});

/**
 * A lineup of three plus a walkover player, sent to the team and declared
 * short-handed by its captain — what the selection screen leaves behind.
 *
 * @param  array<int, User>  $players  the three who play
 */
function departureShortHandedLineup(Interclub $fixture, array $players, User $walkover, User $declaredBy): void
{
    departureLineup($fixture, [...$players, $walkover], published: true);
    $fixture->users()->updateExistingPivot($walkover->id, ['is_walkover' => true]);
    $fixture->update(['short_handed_confirmed_at' => now()->subDay(), 'short_handed_confirmed_by' => $declaredBy->id]);
}

/**
 * The ids of the players of the fixture still marked walkover.
 *
 * @return array<int, int>
 */
function departureWalkovers(Interclub $fixture): array
{
    return $fixture->users()->wherePivot('is_walkover', true)->pluck('users.id')->all();
}

describe('a lineup declared to play with three', function (): void {
    beforeEach(function (): void {
        $this->third = User::factory()->isCompetitor()->create(['first_name' => 'Paul', 'last_name' => 'Troisieme']);
        $this->walkover = User::factory()->isCompetitor()->create(['first_name' => 'Marc', 'last_name' => 'Forfait']);
        $this->team->users()->attach([$this->third->id, $this->walkover->id]);
    });

    it('withdraws the declaration and the walkover when one of the three leaves', function (): void {
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureShortHandedLineup($fixture, [$this->member, $this->stays, $this->third], $this->walkover, $this->captain);

        ($this->declare)($this->member);

        $fixture->refresh()->load('users');

        expect($fixture->isShortHanded())->toBeFalse()
            ->and($fixture->short_handed_confirmed_by)->toBeNull()
            ->and(departureWalkovers($fixture))->toBe([])
            ->and($fixture->users()->wherePivot('is_selected', true)->wherePivotNotNull('selection_confirmed_at')->orderBy('users.id')->pluck('users.id')->all())
            ->toBe([$this->stays->id, $this->third->id, $this->walkover->id])
            ->and(app(InterclubPreparationService::class)->fixtureStatus($fixture))
            ->not->toBeIn(InterclubPreparationService::SETTLED);
    });

    it('withdraws the declaration and the walkover when the walkover player leaves', function (): void {
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureShortHandedLineup($fixture, [$this->stays, $this->third, $this->captain], $this->member, $this->captain);

        ($this->declare)($this->member);

        $fixture->refresh()->load('users');

        expect($fixture->isShortHanded())->toBeFalse()
            ->and(departureWalkovers($fixture))->toBe([])
            ->and($fixture->users()->wherePivot('is_selected', true)->orderBy('users.id')->pluck('users.id')->all())
            ->toBe([$this->captain->id, $this->stays->id, $this->third->id])
            ->and(app(InterclubPreparationService::class)->fixtureStatus($fixture))
            ->not->toBeIn(InterclubPreparationService::SETTLED);
    });

    it('changes nothing else on a lineup that was not declared short-handed', function (): void {
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureLineup($fixture, [$this->member, $this->stays, $this->third, $this->walkover], published: true);
        $before = $fixture->users()->where('users.id', '!=', $this->member->id)->orderBy('users.id')->get()
            ->map(fn (User $player): array => $player->registration->only(['is_selected', 'is_walkover', 'selection_confirmed_at']))->all();

        ($this->declare)($this->member);

        expect($fixture->refresh()->isShortHanded())->toBeFalse()
            ->and($fixture->users()->orderBy('users.id')->get()
                ->map(fn (User $player): array => $player->registration->only(['is_selected', 'is_walkover', 'selection_confirmed_at']))->all())
            ->toBe($before);
    });

    it('keeps the declaration when the member was only available, not lined up', function (): void {
        $fixture = departureFixture($this->team, $this->opponent, 10);
        departureShortHandedLineup($fixture, [$this->stays, $this->third, $this->captain], $this->walkover, $this->captain);
        $fixture->markAvailability($this->member, InterclubAvailability::AVAILABLE);

        ($this->declare)($this->member);

        expect($fixture->refresh()->isShortHanded())->toBeTrue()
            ->and(departureWalkovers($fixture))->toBe([$this->walkover->id]);
    });

    it('leaves the declaration of another team untouched', function (): void {
        $teamB = Team::factory()->create([
            'season_id' => $this->season->id,
            'league_id' => $this->league->id,
            'club_id' => $this->team->club_id,
            'name' => 'D',
            'captain_id' => $this->captain->id,
        ]);
        $others = collect(['Alpha', 'Bravo', 'Charlie', 'Delta'])
            ->map(fn (string $name): User => User::factory()->isCompetitor()->create(['first_name' => $name, 'last_name' => 'Equipe-D']));
        $teamB->users()->attach($others->pluck('id')->all());
        $ours = departureFixture($this->team, $this->opponent, 10);
        departureShortHandedLineup($ours, [$this->member, $this->stays, $this->third], $this->walkover, $this->captain);
        $theirs = departureFixture($teamB, $this->opponent, 12);
        departureShortHandedLineup($theirs, $others->take(3)->all(), $others->last(), $this->captain);

        ($this->declare)($this->member);

        expect($ours->refresh()->isShortHanded())->toBeFalse()
            ->and($theirs->refresh()->isShortHanded())->toBeTrue()
            ->and($theirs->short_handed_confirmed_by)->toBe($this->captain->id)
            ->and(departureWalkovers($theirs))->toBe([$others->last()->id]);
    });

    it('tells the captain which matches are no longer declared short-handed', function (): void {
        $short = departureFixture($this->team, $this->opponent, 10);
        departureShortHandedLineup($short, [$this->member, $this->stays, $this->third], $this->walkover, $this->captain);
        $full = departureFixture($this->team, $this->opponent, 24);
        departureLineup($full, [$this->member, $this->stays, $this->third, $this->walkover], published: true);

        ($this->declare)($this->member);

        Notification::assertSentTo($this->captain, MemberLeftTeamNotification::class, fn (MemberLeftTeamNotification $notification): bool => $notification->interclubIds === [$short->id, $full->id]
            && $notification->shortHandedWithdrawnIds === [$short->id]);
        Notification::assertNotSentTo($this->manager, MemberLeftTeamNotification::class);
    });

    it('says in the mail that the declaration is to be made again', function (): void {
        $short = departureFixture($this->team, $this->opponent, 10);
        $full = departureFixture($this->team, $this->opponent, 24);

        $single = implode(' ', new MemberLeftTeamNotification('Jeanne Depart-Interclub', $this->season->id, ['C'], [$short->id], [$short->id])->toMail($this->captain)->introLines);
        $several = new MemberLeftTeamNotification('Jeanne Depart-Interclub', $this->season->id, ['C'], [$short->id, $full->id], [$short->id])->toMail($this->captain)->introLines;
        $untouched = implode(' ', new MemberLeftTeamNotification('Jeanne Depart-Interclub', $this->season->id, ['C'], [$full->id])->toMail($this->captain)->introLines);

        $declarationWithdrawn = __('The declaration to play with :n has been withdrawn, along with the walkover player: declare it again if the team still plays with :n.', ['n' => 3]);
        $listedWithdrawn = __('(declaration to play with :n withdrawn: declare it again if needed)', ['n' => 3]);

        expect($single)->toContain($declarationWithdrawn)
            ->and(collect($several)->first(fn (string $line): bool => str_contains($line, $short->start_date_time->format('d/m/Y'))))->toContain($listedWithdrawn)
            ->and(collect($several)->first(fn (string $line): bool => str_contains($line, $full->start_date_time->format('d/m/Y'))))->not->toContain($listedWithdrawn)
            ->and($untouched)->not->toContain($declarationWithdrawn);
    });
});

describe('the captain of each team the member played in', function (): void {
    beforeEach(function (): void {
        $this->otherCaptain = User::factory()->isCompetitor()->create(['first_name' => 'Luc', 'last_name' => 'Capitaine-E']);
        // Another category: a player holds one place per category and season.
        $veterans = League::factory()->create(['season_id' => $this->season->id, 'category' => 'VETERANS']);
        $this->teamD = Team::factory()->create([
            'season_id' => $this->season->id,
            'league_id' => $veterans->id,
            'club_id' => $this->team->club_id,
            'name' => 'D',
            'captain_id' => $this->captain->id,
        ]);
        $this->teamE = Team::factory()->create([
            'season_id' => $this->season->id,
            'league_id' => $veterans->id,
            'club_id' => $this->team->club_id,
            'name' => 'E',
            'captain_id' => $this->otherCaptain->id,
        ]);
    });

    it('is told even when the member was on no match to come', function (): void {
        $outcome = ($this->declare)($this->member);

        expect($outcome->captainsTold)->toBe([$this->captain->id]);

        Notification::assertSentTo($this->captain, MemberLeftTeamNotification::class, fn (MemberLeftTeamNotification $notification): bool => $notification->teamNames === ['C']
            && $notification->interclubIds === []
            && $notification->memberName === 'Jeanne Depart-Interclub');
        Notification::assertCount(1);
    });

    it('gets a single mail naming both teams when they captain two of them', function (): void {
        $this->teamD->users()->attach($this->member->id);

        ($this->declare)($this->member);

        Notification::assertSentToTimes($this->captain, MemberLeftTeamNotification::class, 1);
        Notification::assertSentTo($this->captain, MemberLeftTeamNotification::class, fn (MemberLeftTeamNotification $notification): bool => $notification->teamNames === ['C', 'D']);
        Notification::assertCount(1);
    });

    it('gets their own mail, one per captain, each about their own team', function (): void {
        $this->teamE->users()->attach($this->member->id);
        $fixtureC = departureFixture($this->team, $this->opponent, 10);
        departureLineup($fixtureC, [$this->member], published: true);
        $fixtureE = departureFixture($this->teamE, $this->opponent, 12);
        departureLineup($fixtureE, [$this->member], published: false);

        $outcome = ($this->declare)($this->member);

        expect($outcome->captainsTold)->toBe([$this->captain->id, $this->otherCaptain->id]);

        Notification::assertSentTo($this->captain, MemberLeftTeamNotification::class, fn (MemberLeftTeamNotification $notification): bool => $notification->teamNames === ['C']
            && $notification->interclubIds === [$fixtureC->id]);
        Notification::assertSentTo($this->otherCaptain, MemberLeftTeamNotification::class, fn (MemberLeftTeamNotification $notification): bool => $notification->teamNames === ['E']
            && $notification->interclubIds === [$fixtureE->id]);
        Notification::assertCount(2);
    });

    it('tells the other captains when the member captained one of the teams', function (): void {
        $this->teamE->users()->attach($this->member->id);
        $this->teamE->update(['captain_id' => $this->member->id]);

        $outcome = ($this->declare)($this->member);

        expect($outcome->captainsTold)->toBe([$this->captain->id])
            ->and($outcome->teamsWithoutCaptain)->toBe(['E']);

        Notification::assertSentTo($this->captain, MemberLeftTeamNotification::class, fn (MemberLeftTeamNotification $notification): bool => $notification->teamNames === ['C']);
        Notification::assertNotSentTo($this->member, MemberLeftTeamNotification::class);
        Notification::assertCount(1);
    });

    it('is not there to tell for a member in no team', function (): void {
        $loner = User::factory()->isCompetitor()->create(['first_name' => 'Sam', 'last_name' => 'Sans-Equipe']);

        $outcome = ($this->declare)($loner);

        expect($outcome->captainsTold)->toBe([]);

        Notification::assertNothingSent();
    });

    it('says in the mail which team has a free place, even with no match listed', function (): void {
        $mail = new MemberLeftTeamNotification('Jeanne Depart-Interclub', $this->season->id, ['C', 'D'])->toMail($this->captain);
        $text = implode(' ', $mail->introLines);

        expect($text)->toContain(trans_choice('{1} :name has left the club: their place in team :teams is free.|[2,*] :name has left the club: their place in teams :teams is free.', 2, ['name' => 'Jeanne Depart-Interclub', 'teams' => 'C, D']))
            ->and($mail->subject)->toBe(__(':name has left the club', ['name' => 'Jeanne Depart-Interclub']));
    });

    it('never mails a captain who is leaving in the same gesture', function (): void {
        Notification::swap(new ChannelManager(app()));
        $leavingCaptain = User::factory()->isCompetitor()->create(['first_name' => 'Hugo', 'last_name' => 'Capitaine-Partant']);
        Subscription::factory()->for($leavingCaptain)->for($this->season)->create(['status' => 'paid', 'is_competitive' => true]);
        $this->team->update(['captain_id' => $leavingCaptain->id]);
        $this->team->users()->attach($leavingCaptain->id);

        // The member comes first, while the captain still holds the team.
        expect($this->member->id)->toBeLessThan($leavingCaptain->id);

        $component = Livewire::actingAs($this->office)
            ->test('pages::club-admin.users.index')
            ->set('selected', [(string) $this->member->id, (string) $leavingCaptain->id])
            ->set('departureReason', DepartureReason::Moving->value)
            ->set('departureLeftOn', now()->toDateString())
            ->call('bulkDeclareDeparture')
            ->assertHasNoErrors();

        expect($leavingCaptain->notifications()->count())->toBe(0)
            ->and(departureFixturesToastTitle($component))->not->toContain(trans_choice('{1} Captain told.|[2,*] Captains told.', 1));
    });

    it('takes a whole selection off the teams, saying how many captains were told', function (): void {
        $this->teamE->users()->attach($this->stays->id);

        $component = Livewire::actingAs($this->office)
            ->test('pages::club-admin.users.index')
            ->set('selected', [(string) $this->member->id, (string) $this->stays->id])
            ->set('departureReason', DepartureReason::Moving->value)
            ->set('departureLeftOn', now()->toDateString())
            ->call('bulkDeclareDeparture')
            ->assertHasNoErrors();

        expect(departureFixturesToastTitle($component))->toContain(trans_choice('{1} Captain told.|[2,*] Captains told.', 2));

        Notification::assertSentToTimes($this->captain, MemberLeftTeamNotification::class, 2);
        Notification::assertSentToTimes($this->otherCaptain, MemberLeftTeamNotification::class, 1);
        Notification::assertNotSentTo($this->manager, MemberLeftTeamNotification::class);
    });
});
