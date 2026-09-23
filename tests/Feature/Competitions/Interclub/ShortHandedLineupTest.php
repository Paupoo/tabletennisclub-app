<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
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
        ->set('shortHandedOptIn', true)
        ->call('saveSelection')
        ->assertSet('modalMessage', true)
        ->assertSet('sendsShortHanded', true);
});

it('records who declared the team short-handed once the lineup is sent', function (): void {
    $this->freezeTime();

    composeLineup(3)
        ->set('shortHandedOptIn', true)
        ->call('saveSelection')
        ->call('sendLineupToTeam');

    $interclub = $this->interclub->fresh();

    expect($interclub->short_handed_confirmed_at?->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($interclub->short_handed_confirmed_by)->toBe($this->captain->id);

    Queue::assertPushed(SendInterclubSelectionJob::class, 3);
});

it('records nothing when the captain skips sending', function (): void {
    composeLineup(3)
        ->set('shortHandedOptIn', true)
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
        ->set('shortHandedOptIn', true)
        ->call('saveSelection')
        ->assertSet('modalMessage', false)
        ->assertSet('sendsShortHanded', false);

    expect(implode(' ', shortHandedToasts($component)))
        ->toContain('Moins de 3 joueurs')
        ->toContain('48 h');
});

/** A lineup of the first three players, sent, and declared short-handed. */
function sendShortHandedLineup(): void
{
    foreach (test()->players->take(3) as $player) {
        test()->interclub->users()->attach($player->id, ['is_selected' => true, 'selection_confirmed_at' => now()]);
    }

    test()->interclub->update([
        'short_handed_confirmed_at' => now(),
        'short_handed_confirmed_by' => test()->captain->id,
    ]);
}

it('ticks the box again when the drawer reopens on a declared fixture', function (): void {
    sendShortHandedLineup();

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->assertSet('shortHandedOptIn', true);
});

it('withdraws the declaration once a fourth player is found', function (): void {
    sendShortHandedLineup();

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->call('togglePlayer', $this->players[3]->id)
        ->call('saveSelection');

    expect($this->interclub->fresh()->short_handed_confirmed_at)->toBeNull();
});

it('keeps the declaration through a swap and convokes the replacement', function (): void {
    sendShortHandedLineup();

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->call('togglePlayer', $this->players[0]->id)
        ->call('togglePlayer', $this->players[3]->id)
        ->call('saveSelection')
        ->assertSet('isUpdateMode', true)
        ->assertSet('sendsShortHanded', true)
        ->call('sendLineupToTeam');

    expect($this->interclub->fresh()->short_handed_confirmed_at)->not->toBeNull();

    Queue::assertPushed(SendInterclubPlayerRemovedJob::class, fn (SendInterclubPlayerRemovedJob $job): bool => $job->userId === $this->players[0]->id);
    Queue::assertPushed(SendInterclubSelectionJob::class, fn (SendInterclubSelectionJob $job): bool => $job->userId === $this->players[3]->id);
});

it('withdraws the declaration when the captain unticks the box', function (): void {
    sendShortHandedLineup();

    Livewire::actingAs($this->captain)
        ->test('pages::club-events.interclubs.captain-selection')
        ->call('openSelection', $this->interclub->id)
        ->set('shortHandedOptIn', false)
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
        ->set('shortHandedOptIn', true)
        ->call('saveSelection')
        ->assertSet('isUpdateMode', true)
        ->assertSet('sendsShortHanded', true)
        ->call('sendLineupToTeam');

    Queue::assertPushed(SendInterclubPlayerRemovedJob::class, 1);

    foreach ($this->players->take(3) as $player) {
        Queue::assertPushed(SendInterclubSelectionJob::class, fn (SendInterclubSelectionJob $job): bool => $job->userId === $player->id);
    }
});

it('offers the box only between the minimum and a full team', function (int $count, bool $offered): void {
    $component = composeLineup($count);

    $offered
        ? $component->assertSeeHtml('wire:model.live="shortHandedOptIn"')
        : $component->assertDontSeeHtml('wire:model.live="shortHandedOptIn"');
})->with([
    'two of four' => [2, false],
    'three of four' => [3, true],
    'four of four' => [4, false],
]);

it('warns in the send modal that the team plays short and what it costs', function (): void {
    composeLineup(3)
        ->set('shortHandedOptIn', true)
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
        ->set('shortHandedOptIn', true)
        ->call('saveSelection')
        ->assertSet('modalMessage', true)
        ->assertSet('sendsShortHanded', true)
        ->call('sendLineupToTeam');

    expect($this->interclub->fresh()->isShortHanded())->toBeTrue();

    Queue::assertPushed(SendInterclubSelectionJob::class, 3);
});
