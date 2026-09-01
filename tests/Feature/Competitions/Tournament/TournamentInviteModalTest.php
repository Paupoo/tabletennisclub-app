<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Livewire\Livewire;

/*
 * La modale de confirmation d'envoi s'ouvrait vide : ni « Envoyer maintenant »,
 * ni « Annuler », rien à confirmer.
 *
 * `x-app-modal` ne rend son corps et ses actions que si `:open` est vrai côté
 * serveur -- c'est ce qui évite d'expédier 27 ko de liste de membres dans une
 * modale fermée. Le bouton, lui, levait le drapeau par une affectation Alpine
 * (`$wire.showInviteModal = true`), qui est un set différé : le dialogue
 * s'ouvrait côté client, mais le serveur n'avait rien re-rendu.
 */

function inviteModalTournament(): Tournament
{
    return Tournament::factory()->create([
        'status' => TournamentStatusEnum::PUBLISHED,
        'duration_minutes' => 180,
        'pool_size' => 4,
        'nb_pools' => 2,
        'nb_qualifiers_per_pool' => 2,
        'sets_to_win' => 3,
        'logistics_buffer_minutes' => 3,
        'match_type' => 'single',
        'has_handicap_points' => false,
        'deuce_enabled' => false,
    ]);
}

it('opens the modal through the server, not through Alpine alone', function (): void {
    $html = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.wizard', ['tournament' => inviteModalTournament()])
        ->set('step', '4')
        ->set('selectedMembers', [User::factory()->create()->id])
        ->html();

    // Le déclencheur doit provoquer un rendu, sans quoi la modale arrive vide.
    expect($html)->toContain('$set(\'showInviteModal\', true)')
        ->and($html)->not->toContain('$wire.showInviteModal = true');
});

it('has something to confirm once it is open', function (): void {
    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.wizard', ['tournament' => inviteModalTournament()])
        ->set('step', '4')
        ->set('showInviteModal', true)
        ->assertSee(__('Send now'))
        ->assertSee(__('Cancel'));
});

it('still ships nothing while the modal is shut', function (): void {
    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.wizard', ['tournament' => inviteModalTournament()])
        ->set('step', '4')
        ->assertDontSee(__('Send now'));
});
