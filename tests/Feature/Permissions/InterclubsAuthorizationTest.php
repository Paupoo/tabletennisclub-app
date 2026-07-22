<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Interclubs is the domain where a permission is not enough on its own: a captain
| is a relation, never a délégation, and must compose and report for their own
| teams while holding nothing. InterclubPolicy::update() and delete() also used to
| `return true` for any authenticated member.
*/

beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
    $this->club = Club::factory()->ownClub()->create();

    $this->captain = User::factory()->isCompetitor()->create();
    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => $this->captain->id,
        'club_id' => $this->club->id,
    ]);
    $this->fixture = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'total_players' => 4,
        'start_date_time' => now()->addDays(7),
    ]);

    $this->otherTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => User::factory()->create()->id,
        'club_id' => $this->club->id,
    ]);
    $this->otherFixture = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->otherTeam->id,
        'total_players' => 4,
        'start_date_time' => now()->addDays(7),
    ]);
});

describe('a captain holds no delegation, and still leads their team', function (): void {
    it('reaches the selections and results screens', function (): void {
        expect($this->captain->getRoleNames())->toBeEmpty();

        $this->actingAs($this->captain)->get(route('admin.interclubs.captain-selection'))->assertOk();
        $this->actingAs($this->captain)->get(route('admin.interclubs.results'))->assertOk();
    });

    it('may compose their own lineup but not another team\'s', function (): void {
        expect($this->captain)
            ->can('selectLineup', $this->fixture)->toBeTrue()
            ->can('selectLineup', $this->otherFixture)->toBeFalse();
    });

    it('may not manage the calendar', function (): void {
        expect($this->captain)
            ->can('update', $this->fixture)->toBeFalse()
            ->can('delete', $this->fixture)->toBeFalse()
            ->can('create', Interclub::class)->toBeFalse();
    });
});

describe('the delegations', function (): void {
    it('lets a club-wide selector compose for any team', function (): void {
        $selector = User::factory()->withRole(Role::SELECTIONS)->create();

        expect($selector)
            ->can('selectLineup', $this->fixture)->toBeTrue()
            ->can('selectLineup', $this->otherFixture)->toBeTrue()
            ->can('update', $this->fixture)->toBeFalse();
    });

    it('lets the interclubs delegate manage the calendar and the teams', function (): void {
        $delegate = User::factory()->withRole(Role::INTERCLUBS)->create();

        expect($delegate)
            ->can('update', $this->fixture)->toBeTrue()
            ->can('delete', $this->fixture)->toBeTrue()
            ->can('update', $this->team)->toBeTrue();
    });

    it('does not let the interclubs delegate compose lineups — that is the selections duty', function (): void {
        $delegate = User::factory()->withRole(Role::INTERCLUBS)->create();

        expect($delegate)
            ->can('selectLineup', $this->fixture)->toBeFalse()
            ->can('selectLineup', $this->otherFixture)->toBeFalse();

        $this->actingAs($delegate)->get(route('admin.interclubs.captain-selection'))->assertForbidden();
    });

    it('no longer opens the configuration screens on committee membership alone', function (string $routeName): void {
        $this->actingAs(User::factory()->isCommitteeMember()->create())
            ->get(route($routeName))
            ->assertForbidden();
    })->with([
        'admin.interclubs.teams',
        'admin.interclubs.clubs',
        'admin.interclubs.division-setup',
        'admin.interclubs.interclubs',
    ]);
});

describe('the fixtures are no longer open to everyone', function (): void {
    it('refuses a plain member what the policy used to return true for', function (): void {
        $member = User::factory()->create();

        expect($member)
            ->can('update', $this->fixture)->toBeFalse()
            ->can('delete', $this->fixture)->toBeFalse()
            ->can('view', $this->fixture)->toBeFalse();
    });

    it('never hard-deletes a fixture', function (): void {
        expect(User::factory()->isAdmin()->create()->can('forceDelete', $this->fixture))->toBeFalse();
    });
});
