<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;

/*
 * La clé `Open` se traduit « Ouvert » : c'est l'adjectif dont deux badges d'état
 * ont besoin (`admin.shared.status-badge`, `registration-management`). Sur la
 * carte d'un pack elle libellait un bouton, qui annonçait donc un état au lieu
 * de nommer son action. Son icône mentait de la même façon : la flèche sortant
 * d'un cadre promet un nouvel onglet, alors que `openPack()` ne fait que poser
 * `selectedPackId` — la grille cède la place à la fiche, dans la même page.
 */

it('names the pack action instead of describing a state', function (): void {
    $admin = User::factory()->isAdmin()->create();
    $season = makeActiveSeason();
    makeTrainingPack($season);

    Livewire::actingAs($admin)
        ->test('pages::club-events.trainings.index')
        ->assertSee(__('Details'))
        ->assertDontSee(__('Open'));
})->group('training');
