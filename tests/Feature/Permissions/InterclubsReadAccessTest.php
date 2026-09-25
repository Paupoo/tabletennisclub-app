<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

pest()->group('interclubs', 'permissions');

/*
| The interclub screens, read by the committee.
|
| Their routes used to be the only guard: every write on the teams, calendar and
| opposing-clubs screens trusted that nobody without the duty could reach them.
| Opened to `interclubs.view`, each write now answers for itself, and the markup
| offers a reader nothing it would refuse.
*/

beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
    $this->ownClub = Club::factory()->ownClub()->create();
    $this->opponent = Club::factory()->create();

    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $league->id,
        'club_id' => $this->ownClub->id,
    ]);
    $this->fixture = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $league->id,
        'visited_team_id' => $this->team->id,
        'total_players' => 4,
        'start_date_time' => now()->addDays(7),
    ]);

    $this->reader = User::factory()->isCommitteeMember()->create();
    $this->delegate = User::factory()->withRole(Role::INTERCLUBS)->create();
});

describe('the teams', function (): void {
    it('lists them for a reader without a way to change them', function (): void {
        Livewire::actingAs($this->reader)
            ->test('pages::club-events.interclubs.teams.index')
            ->assertSee($this->team->name)
            ->assertSee(route('admin.interclubs.teams.show', $this->team))
            ->assertDontSee(route('admin.interclubs.teams.edit', $this->team))
            ->assertDontSee(route('admin.interclubs.teams.builder'))
            ->assertDontSee('confirmDelete(' . $this->team->id . ')');
    });

    it('refuses every write to a reader', function (string $method, array $arguments): void {
        Livewire::actingAs($this->reader)
            ->test('pages::club-events.interclubs.teams.index')
            ->set('teamToDelete', $this->team->id)
            ->call($method, ...$arguments)
            ->assertForbidden();

        expect(Team::find($this->team->id))->not->toBeNull();
    })->with([
        'confirmDelete' => ['confirmDelete', [1]],
        'delete' => ['delete', []],
        'deleteAll' => ['deleteAll', []],
        'createTeam' => ['createTeam', []],
    ]);
});

describe('the calendar', function (): void {
    it('shows the fixtures to a reader without a way to change them', function (): void {
        Livewire::actingAs($this->reader)
            ->test('pages::club-events.interclubs.interclubs')
            ->assertDontSee('openCreateModal')
            ->assertDontSee('openEditModal(' . $this->fixture->id . ')')
            ->assertDontSee('confirmDelete(' . $this->fixture->id . ')');
    });

    it('still offers them to the interclubs delegate', function (): void {
        Livewire::actingAs($this->delegate)
            ->test('pages::club-events.interclubs.interclubs')
            ->assertSee('openEditModal(' . $this->fixture->id . ')');
    });

    it('refuses every write to a reader', function (string $method, array $arguments): void {
        Livewire::actingAs($this->reader)
            ->test('pages::club-events.interclubs.interclubs')
            ->set('deletingInterclubId', $this->fixture->id)
            ->call($method, ...$arguments)
            ->assertForbidden();

        expect(Interclub::find($this->fixture->id))->not->toBeNull();
    })->with([
        'openCreateModal' => ['openCreateModal', []],
        'openEditModal' => ['openEditModal', [1]],
        'confirmDelete' => ['confirmDelete', [1]],
        'delete' => ['delete', []],
        'save' => ['save', []],
    ]);
});

describe('the opposing clubs', function (): void {
    it('lists them for a reader without a way to change them', function (): void {
        Livewire::actingAs($this->reader)
            ->test('pages::club-events.interclubs.clubs')
            ->assertSee($this->opponent->name)
            ->assertDontSee('openCreateModal')
            ->assertDontSee('openEditModal(' . $this->opponent->id . ')')
            ->assertDontSee('confirmDelete(' . $this->opponent->id . ')');
    });

    it('refuses every write to a reader', function (string $method, array $arguments): void {
        Livewire::actingAs($this->reader)
            ->test('pages::club-events.interclubs.clubs')
            ->set('deletingClubId', $this->opponent->id)
            ->call($method, ...$arguments)
            ->assertForbidden();

        expect(Club::find($this->opponent->id))->not->toBeNull();
    })->with([
        'openCreateModal' => ['openCreateModal', []],
        'openEditModal' => ['openEditModal', [1]],
        'confirmDelete' => ['confirmDelete', [1]],
        'delete' => ['delete', []],
        'save' => ['save', []],
    ]);
});

describe('the selections', function (): void {
    beforeEach(function (): void {
        $this->captain = User::factory()->isCompetitor()->create();
        $this->team->update(['captain_id' => $this->captain->id]);

        $this->otherTeam = Team::factory()->create([
            'season_id' => $this->season->id,
            'league_id' => $this->team->league_id,
            'club_id' => $this->ownClub->id,
            'name' => 'Équipe Voisine',
            // TeamFactory hands the captaincy to user #1 — here, the reader.
            'captain_id' => User::factory()->create()->id,
        ]);

        $this->selected = User::factory()->create(['last_name' => 'Titulaire']);
        $this->fixture->users()->attach($this->selected->id, ['is_selected' => true]);
    });

    it('lets a reader browse every team, not only the ones they captain', function (): void {
        Livewire::actingAs($this->reader)
            ->test('pages::club-events.interclubs.captain-selection')
            ->assertSee('Équipe Voisine');
    });

    it('shows a reader the lineup already chosen for an upcoming match', function (): void {
        Livewire::actingAs($this->reader)
            ->test('pages::club-events.interclubs.captain-selection')
            ->call('selectTeam', $this->team->id)
            ->assertSee('Titulaire');
    });

    it('offers a reader no way to compose or to poll the team', function (): void {
        Livewire::actingAs($this->reader)
            ->test('pages::club-events.interclubs.captain-selection')
            ->call('selectTeam', $this->team->id)
            ->assertDontSee('openSelection(' . $this->fixture->id . ')')
            ->assertDontSee('confirmAvailabilityRequest(' . $this->fixture->id . ')');
    });

    it('refuses a reader the composing actions', function (string $method): void {
        Livewire::actingAs($this->reader)
            ->test('pages::club-events.interclubs.captain-selection')
            ->call($method, $this->fixture->id)
            ->assertForbidden();
    })->with(['openSelection', 'confirmAvailabilityRequest']);

    it('still lets the captain compose for their own team', function (): void {
        Livewire::actingAs($this->captain)
            ->test('pages::club-events.interclubs.captain-selection')
            ->assertSee('openSelection(' . $this->fixture->id . ')')
            ->assertDontSee('Équipe Voisine');
    });
});

describe('the results', function (): void {
    beforeEach(function (): void {
        $this->team->update(['captain_id' => User::factory()->create()->id]);
        $this->result = InterclubResult::factory()->create([
            'team_id' => $this->team->id,
            'season_id' => $this->season->id,
            'opponent_name' => 'Adversaire Lointain',
        ]);
    });

    it('shows a reader every team\'s results without a way to change them', function (): void {
        Livewire::actingAs($this->reader)
            ->test('pages::club-events.interclubs.results')
            ->assertSee('Adversaire Lointain')
            ->assertDontSee('openEditModal(' . $this->result->id . ')')
            ->assertDontSee('confirmDelete(' . $this->result->id . ')')
            ->assertDontSee('openTeamForfeitModal(' . $this->team->id . ')')
            ->assertDontSee('updateFinalPosition(' . $this->team->id);
    });

    it('refuses every write to a reader', function (string $method, array $arguments): void {
        Livewire::actingAs($this->reader)
            ->test('pages::club-events.interclubs.results')
            ->set('deletingInterclubResultId', $this->result->id)
            ->set('forfeitingTeamId', $this->team->id)
            ->call($method, ...$arguments)
            ->assertForbidden();

        expect(InterclubResult::find($this->result->id))->not->toBeNull();
    })->with([
        'openEditModal' => ['openEditModal', [1]],
        'confirmDelete' => ['confirmDelete', [1]],
        'delete' => ['delete', []],
        'openTeamForfeitModal' => ['openTeamForfeitModal', [1]],
        'declareTeamForfeit' => ['declareTeamForfeit', []],
        'updateFinalPosition' => ['updateFinalPosition', [1, '1st']],
    ]);
});

describe('a fixture seen from a team file', function (): void {
    it('opens to a reader, since the team file links to it', function (): void {
        $this->actingAs($this->reader)
            ->get(route('admin.interclubs.my-match', $this->fixture))
            ->assertOk();
    });

    it('opens to any member too, since the club calendar links to it', function (): void {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.interclubs.my-match', $this->fixture))
            ->assertOk();
    });
});
