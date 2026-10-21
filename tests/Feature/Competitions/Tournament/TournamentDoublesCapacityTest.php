<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * A doubles pool holds pairs, and players register one by one. The pool size
 * counts pairs — the simulator and the table count always read it so — and
 * the cap counts players, two per pair. An odd cap would accept a player who
 * can never be paired, and the pools can only be drawn on an empty bench.
 */

function newTournamentWizard(): Testable
{
    return Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.wizard');
}

it('suggests two players per pair in doubles', function (): void {
    newTournamentWizard()
        ->set('matchType', 'double')
        ->set('nb_poules', 4)
        ->set('pool_size', 4)
        ->assertSet('maxUsers', 32);
});

it('follows the structure again when singles become doubles', function (): void {
    newTournamentWizard()
        ->set('nb_poules', 4)
        ->set('pool_size', 4)
        ->assertSet('maxUsers', 16)
        ->set('matchType', 'double')
        ->assertSet('maxUsers', 32)
        ->set('matchType', 'single')
        ->assertSet('maxUsers', 16);
});

it('refuses an odd cap in doubles', function (): void {
    newTournamentWizard()
        ->set('name', 'Tournoi de double')
        ->set('tournamentDate', '2026-11-29')
        ->set('matchType', 'double')
        ->set('maxUsers', 31)
        ->call('save')
        ->assertHasErrors(['maxUsers']);
});

it('labels the pool size in pairs for doubles', function (): void {
    newTournamentWizard()
        ->set('matchType', 'double')
        ->assertSee(__('Pairs per pool(*)'));
});

it('creates a new tournament from the wizard', function (): void {
    newTournamentWizard()
        ->set('name', 'Tournoi de double')
        ->set('tournamentDate', '2026-11-29')
        ->set('matchType', 'double')
        ->call('save')
        ->assertHasNoErrors();

    expect(Tournament::where('name', 'Tournoi de double')->value('status'))
        ->toBe(TournamentStatusEnum::DRAFT);
});
