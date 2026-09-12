<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A place in a team is a declaration, not an option: a player holds one core per
 * category and per season. Playing up for a single fixture goes through the
 * substitute search instead, which never touches the roster.
 *
 * The rule lives on the pivot rather than on the screens that write it, because
 * every path — sync(), attach(), a seeder, tinker, the screen nobody has written
 * yet — goes through the pivot, and none of them can be trusted to remember.
 */
beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->player = User::factory()->isCompetitor()->create();

    $this->teamIn = function (string $category, string $name = 'A', ?int $seasonId = null): Team {
        $league = League::factory()->create([
            'season_id' => $seasonId ?? $this->season->id,
            'category' => $category,
        ]);

        return Team::factory()->create([
            'season_id' => $seasonId ?? $this->season->id,
            'league_id' => $league->id,
            'name' => $name,
            'captain_id' => null,
        ]);
    };
});

it('refuses a player already in another team of the same category', function (): void {
    $teamE = ($this->teamIn)('MEN', 'E');
    $teamD = ($this->teamIn)('MEN', 'D');

    $teamE->users()->attach($this->player->id);

    expect(fn () => $teamD->users()->attach($this->player->id))
        ->toThrow(DomainException::class);

    expect($this->player->teams()->pluck('teams.id')->all())->toBe([$teamE->id]);
});

it('lets the same player hold a place in another category', function (): void {
    $men = ($this->teamIn)('MEN', 'E');
    $veterans = ($this->teamIn)('VETERANS', 'A');

    $men->users()->attach($this->player->id);
    $veterans->users()->attach($this->player->id);

    expect($this->player->teams()->count())->toBe(2);
});

it('does not reach across seasons', function (): void {
    $lastSeason = Season::factory()->create([
        'start_at' => now()->subYear()->startOfYear(),
        'end_at' => now()->subYear()->endOfYear(),
    ]);

    $thisYear = ($this->teamIn)('MEN', 'D');
    $lastYear = ($this->teamIn)('MEN', 'D', $lastSeason->id);

    $lastYear->users()->attach($this->player->id);
    $thisYear->users()->attach($this->player->id);

    expect($this->player->teams()->count())->toBe(2);
});

it('treats a team without a division as a category of its own', function (): void {
    $orphanA = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => null,
        'name' => 'A',
        'captain_id' => null,
    ]);
    $orphanB = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => null,
        'name' => 'B',
        'captain_id' => null,
    ]);

    $orphanA->users()->attach($this->player->id);

    expect(fn () => $orphanB->users()->attach($this->player->id))
        ->toThrow(DomainException::class);
});

it('does not fight a team saving its own roster again', function (): void {
    $team = ($this->teamIn)('MEN', 'E');
    $mate = User::factory()->isCompetitor()->create();

    $team->users()->sync([$this->player->id]);
    $team->users()->sync([$this->player->id, $mate->id]);

    expect($team->users()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$this->player->id, $mate->id])->sort()->values()->all());
});

it('names the team standing in the way', function (): void {
    $teamE = ($this->teamIn)('MEN', 'E');
    $teamD = ($this->teamIn)('MEN', 'D');

    $teamE->users()->attach($this->player->id);

    expect(fn () => $teamD->users()->attach($this->player->id))
        ->toThrow(DomainException::class, __('This player already holds a place in team :team for this category.', ['team' => 'E']));
});

/*
|--------------------------------------------------------------------------
| Ce que l'écran en montre
|--------------------------------------------------------------------------
|
| La règle ne vaut rien si le sélectionneur ne peut pas l'exercer. Masquer un
| joueur déjà pris le ferait passer pour inéligible ; la ligne doit dire qui le
| tient, et le clic doit ouvrir le déplacement.
*/

it('shows which team already holds a candidate, and offers the move', function (): void {
    $held = User::factory()->isCompetitor()->create(['ranking' => 'C6']);

    $teamE = ($this->teamIn)('MEN', 'E');
    $teamD = ($this->teamIn)('MEN', 'D');
    $teamE->users()->attach($held->id);

    $admin = User::factory()->isAdmin()->create(['licence' => null]);

    $component = Livewire::actingAs($admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $teamD]);

    $component->assertSee($held->last_name)
        ->assertSee(__('In team :team', ['team' => 'E']));

    $component->call('toggleMember', $held->id)
        ->assertSet('showMoveModal', true)
        ->assertSee(__('Move this player?'));
});
