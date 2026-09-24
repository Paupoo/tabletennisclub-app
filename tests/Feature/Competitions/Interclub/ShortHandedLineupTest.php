<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\InterclubPreparationService;
use App\Jobs\SendInterclubPlayerRemovedJob;
use App\Jobs\SendInterclubSelectionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;

uses(RefreshDatabase::class);

/**
 * Jouer à 3.
 *
 * Les règlements laissent une équipe messieurs débuter à trois de ses quatre
 * joueurs (C.25.6). Une sélection sous le complet restait pourtant un brouillon
 * que rien ne permettait d'envoyer : le capitaine qui n'avait que trois joueurs
 * ne pouvait convoquer personne. Il le peut désormais, mais seulement en le
 * déclarant — une case à cocher, pour être certain qu'il n'a pas d'autre
 * solution — et la déclaration n'est enregistrée qu'une fois l'équipe prévenue.
 */
beforeEach(function (): void {
    Queue::fake();

    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    $this->captain = User::factory()->isCompetitor()->create();
    $this->players = User::factory()->isCompetitor()->count(4)->create();

    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => $this->captain->id,
        'club_id' => Club::factory()->ownClub()->create()->id,
    ]);

    $this->team->users()->attach([$this->captain->id, ...$this->players->pluck('id')]);

    $this->interclub = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'total_players' => 4,
        'start_date_time' => now()->addDays(3),
    ]);
});

/** Opens the drawer and ticks the first $count players of the roster. */
function composeLineup(int $count): Testable
{
    $component = Livewire::actingAs(test()->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', test()->interclub->id);

    foreach (test()->players->take($count) as $player) {
        $component->call('togglePlayer', $player->id);
    }

    return $component;
}

it('opens the send modal on three of four once the captain declares it', function (): void {
    composeLineup(3)
        ->call('designateWalkover', $this->players[3]->id)
        ->call('saveSelection')
        ->assertSet('modalMessage', true)
        ->assertSet('sendsShortHanded', true);
});

it('records who declared the team short-handed once the lineup is sent', function (): void {
    $this->freezeTime();

    composeLineup(3)
        ->call('designateWalkover', $this->players[3]->id)
        ->call('saveSelection')
        ->call('sendLineupToTeam');

    $interclub = $this->interclub->fresh();

    expect($interclub->short_handed_confirmed_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($interclub->short_handed_confirmed_by)->toBe($this->captain->id);

    // Les trois qui jouent, et le WO : il figure sur la feuille.
    Queue::assertPushed(SendInterclubSelectionJob::class, 4);
});

it('records nothing when the captain skips sending', function (): void {
    composeLineup(3)
        ->call('designateWalkover', $this->players[3]->id)
        ->call('saveSelection')
        ->call('skipSending');

    expect($this->interclub->fresh()->short_handed_confirmed_at)->toBeNull();
});

/**
 * What the toasts raised during the last request say, title and description.
 *
 * @return string[]
 */
function shortHandedToasts(Testable $component): array
{
    $texts = [];

    foreach ($component->effects['xjs'] ?? [] as $script) {
        $expression = is_array($script) ? ($script['expression'] ?? '') : (string) $script;

        if (preg_match('/^toast\((.*)\)$/s', $expression, $matches) !== 1) {
            continue;
        }

        $toast = json_decode($matches[1], true)['toast'] ?? [];
        $texts[] = trim(($toast['title'] ?? '') . ' ' . ($toast['description'] ?? ''));
    }

    return $texts;
}

it('ignores the declaration below the minimum and says the fixture cannot be played', function (): void {
    $component = composeLineup(2)
        ->call('designateWalkover', $this->players[3]->id)
        ->call('saveSelection')
        ->assertSet('modalMessage', false)
        ->assertSet('sendsShortHanded', false);

    expect(implode(' ', shortHandedToasts($component)))
        ->toContain('Moins de 3 joueurs')
        ->toContain('48 h');
});

/** A lineup of the first three players and the fourth as walkover, sent and declared short-handed. */
function sendShortHandedLineup(): void
{
    foreach (test()->players->take(3) as $player) {
        test()->interclub->users()->attach($player->id, ['is_selected' => true, 'selection_confirmed_at' => now()]);
    }

    test()->interclub->users()->attach(test()->players[3]->id, ['is_selected' => true, 'is_walkover' => true, 'selection_confirmed_at' => now()]);

    test()->interclub->update([
        'short_handed_confirmed_at' => now(),
        'short_handed_confirmed_by' => test()->captain->id,
    ]);
}

it('names the walkover player again when the drawer reopens on a declared fixture', function (): void {
    sendShortHandedLineup();

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->assertSet('walkoverPlayerId', $this->players[3]->id);
});

it('withdraws the declaration once a fourth player is found', function (): void {
    sendShortHandedLineup();

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        // Le capitaine lui-même se libère : il prend la place du WO.
        ->call('togglePlayer', $this->captain->id)
        ->assertSet('walkoverPlayerId', null)
        ->call('saveSelection');

    $interclub = $this->interclub->fresh();

    expect($interclub->short_handed_confirmed_at)->toBeNull()
        ->and($interclub->getSelectedPlayers()->pluck('id')->all())->not->toContain($this->players[3]->id);
});

it('keeps the declaration through a swap and convokes the replacement', function (): void {
    sendShortHandedLineup();

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->call('togglePlayer', $this->players[0]->id)
        ->call('togglePlayer', $this->captain->id)
        ->call('saveSelection')
        ->assertSet('isUpdateMode', true)
        ->assertSet('sendsShortHanded', true)
        ->call('sendLineupToTeam');

    expect($this->interclub->fresh()->short_handed_confirmed_at)->not->toBeNull();

    Queue::assertPushed(SendInterclubPlayerRemovedJob::class, fn (SendInterclubPlayerRemovedJob $job): bool => $job->userId === $this->players[0]->id);
    Queue::assertPushed(SendInterclubSelectionJob::class, fn (SendInterclubSelectionJob $job): bool => $job->userId === $this->captain->id);
});

it('withdraws the declaration when the captain unticks the walkover player', function (): void {
    sendShortHandedLineup();

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->call('togglePlayer', $this->players[3]->id)
        ->assertSet('walkoverPlayerId', null)
        ->call('saveSelection');

    expect($this->interclub->fresh()->short_handed_confirmed_at)->toBeNull();
});

it('withdraws the declaration when the lineup falls below the minimum', function (): void {
    sendShortHandedLineup();

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->call('togglePlayer', $this->players[0]->id)
        ->call('saveSelection');

    expect($this->interclub->fresh()->short_handed_confirmed_at)->toBeNull();
});

/*
| Le cas qui a tout déclenché : une compo envoyée à quatre, puis un désistement.
| Les trois restants gardent leur convocation ; s'ils ne sont pas prévenus que
| l'on joue à trois, ils l'apprennent à la table.
*/
it('tells the three who remain that the team now plays short', function (): void {
    foreach ($this->players as $player) {
        $this->interclub->users()->attach($player->id, ['is_selected' => true, 'selection_confirmed_at' => now()]);
    }

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->call('togglePlayer', $this->players[3]->id)
        // Celui qui ne peut plus venir reste sur la feuille, inscrit WO.
        ->call('designateWalkover', $this->players[3]->id)
        ->call('saveSelection')
        ->assertSet('isUpdateMode', true)
        ->assertSet('sendsShortHanded', true)
        ->call('sendLineupToTeam');

    Queue::assertNotPushed(SendInterclubPlayerRemovedJob::class);

    foreach ($this->players->take(3) as $player) {
        Queue::assertPushed(SendInterclubSelectionJob::class, fn (SendInterclubSelectionJob $job): bool => $job->userId === $player->id);
    }
});

it('offers a walkover only when the lineup stands at the minimum', function (int $count, bool $offered): void {
    $component = composeLineup($count);

    $offered
        ? $component->assertSeeHtml('designateWalkover(')
        : $component->assertDontSeeHtml('designateWalkover(');
})->with([
    'two of four' => [2, false],
    'three of four' => [3, true],
    'four of four' => [4, false],
]);

it('warns in the send modal that the team plays short and what it costs', function (): void {
    composeLineup(3)
        ->call('designateWalkover', $this->players[3]->id)
        ->call('saveSelection')
        ->assertSee('Vous jouerez à 3 sur 4')
        ->assertSee('Les matchs du joueur manquant seront perdus');
});

it('reads as settled on the captain screen, like any lineup sent', function (): void {
    sendShortHandedLineup();

    $component = Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection');

    expect(collect($component->viewData('matchGroups')['controlled'])->pluck('id'))->toContain($this->interclub->id);

    $component->assertSee('Composition envoyée');
});

it('lets the captain declare it later, on a lineup already cut to three', function (): void {
    // Le désistement a été enregistré sans la case : seul le retiré a été prévenu.
    foreach ($this->players->take(3) as $player) {
        $this->interclub->users()->attach($player->id, ['is_selected' => true, 'selection_confirmed_at' => now()]);
    }

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->call('designateWalkover', $this->players[3]->id)
        ->call('saveSelection')
        ->assertSet('modalMessage', true)
        ->assertSet('sendsShortHanded', true)
        ->call('sendLineupToTeam');

    expect($this->interclub->fresh()->isShortHanded())->toBeTrue();

    Queue::assertPushed(SendInterclubSelectionJob::class, 4);
});

/*
| Le joueur WO. Jouer à 3, c'est inscrire un quatrième joueur absent sur la
| feuille : il est aligné (C.20.1 le bloque ailleurs la semaine) mais ne joue
| pas. Le capitaine doit le nommer ; on ne déclare plus « à 3 » sans lui.
*/
describe('the walkover player', function (): void {
    it('lines up the walkover player beside the three who play', function (): void {
        composeLineup(3)
            ->call('designateWalkover', $this->players[3]->id)
            ->assertSet('walkoverPlayerId', $this->players[3]->id)
            ->call('saveSelection');

        $lineup = $this->interclub->fresh()->users->keyBy('id');

        expect($lineup)->toHaveCount(4)
            ->and((bool) $lineup[$this->players[3]->id]->registration->is_selected)->toBeTrue()
            ->and((bool) $lineup[$this->players[3]->id]->registration->is_walkover)->toBeTrue()
            ->and((bool) $lineup[$this->players[0]->id]->registration->is_walkover)->toBeFalse();
    });

    it('offers no walkover away from the minimum', function (): void {
        composeLineup(2)
            ->call('designateWalkover', $this->players[3]->id)
            ->assertSet('walkoverPlayerId', null)
            ->assertSet('selectedPlayerIds', $this->players->take(2)->pluck('id')->all());
    });

    it('refuses a walkover player already lined up elsewhere that week', function (): void {
        $otherTeam = Team::factory()->create([
            'season_id' => $this->season->id,
            'league_id' => $this->league->id,
            'captain_id' => $this->captain->id,
            'club_id' => $this->team->club_id,
        ]);

        Interclub::factory()->create([
            'season_id' => $this->season->id,
            'league_id' => $this->league->id,
            'visited_team_id' => $otherTeam->id,
            'week_number' => $this->interclub->week_number,
            'start_date_time' => now()->addDays(4),
        ])->select($this->players[3]);

        composeLineup(3)
            ->call('designateWalkover', $this->players[3]->id)
            ->assertSet('walkoverPlayerId', null);
    });

    it('bars the walkover player from the other teams of the category that week', function (): void {
        sendShortHandedLineup();

        $otherTeam = Team::factory()->create([
            'season_id' => $this->season->id,
            'league_id' => $this->league->id,
            'captain_id' => $this->captain->id,
            'club_id' => $this->team->club_id,
        ]);

        $sameWeek = Interclub::factory()->create([
            'season_id' => $this->season->id,
            'league_id' => $this->league->id,
            'visited_team_id' => $otherTeam->id,
            'week_number' => $this->interclub->week_number,
            'total_players' => 4,
            'start_date_time' => now()->addDays(4),
        ]);

        Livewire::actingAs($this->captain)
            ->test('pages::club-events.interclubs.captain-selection')
            ->call('openSelection', $sameWeek->id)
            ->call('togglePlayer', $this->players[3]->id)
            ->assertSet('selectedPlayerIds', []);
    });

    it('tells the players on their match list that the team plays with three', function (): void {
        sendShortHandedLineup();

        Livewire::actingAs($this->players[0])
            ->test('pages::club-events.interclubs.my-matches')
            ->assertSee(__('with :n', ['n' => 3]))
            ->assertDontSee(__('with :n', ['n' => 4]));
    });

    it('reads as short-handed, not as a full lineup, once sent', function (): void {
        sendShortHandedLineup();

        expect(app(InterclubPreparationService::class)
            ->fixtureStatus($this->interclub->fresh()->load('users')))->toBe('short');
    });
});
