<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Le wizard, sur un tournoi existant, à l'étape configuration.
 *
 * Le plafond vaut exactement la capacité de la structure (2 poules × 4), si
 * bien que `maxUsersManual` est faux et que le plafond suit les poules — c'est
 * la configuration du tournoi semé, et celle où le bug se voit.
 */
function wizardAtSetup(): Testable
{
    return Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.wizard', ['tournament' => paymentTournament(['max_users' => 8])])
        ->set('step', '1');
}

describe('champ numérique vidé', function (): void {
    /*
     * Sélectionner « 4 » et taper « 5 » passe par un champ vide, et avec un
     * debounce c'est cet état vide qui part au serveur. Livewire ne peut pas
     * écrire "" dans un `public int`, laissait la propriété non initialisée,
     * et le hook `updatedNbPoules()` la relisait aussitôt : la page mourait sur
     * « Property [$nb_poules] not found on component ».
     */
    it('ne casse pas quand on vide le nombre de poules', function (): void {
        wizardAtSetup()
            ->assertSet('nb_poules', 2)
            ->set('nb_poules', '')
            ->assertSet('nb_poules', 2)
            ->assertHasNoErrors();
    });

    it('reprend la saisie là où elle en était', function (): void {
        wizardAtSetup()
            ->set('nb_poules', '')
            ->set('nb_poules', 5)
            ->assertSet('nb_poules', 5)
            // Le plafond suit la structure tant qu'on ne l'a pas fixé à la main :
            // 5 poules de 4. C'est le geste qui déclenchait le plantage.
            ->assertSet('maxUsers', 20);
    });

    it('protège tous les champs numériques de la structure', function (): void {
        $component = wizardAtSetup();

        foreach ([
            'logistics_buffer' => 3,
            'maxUsers' => 8,
            'nb_qualifies' => 2,
            'nb_tables' => 8,
            'tournament_minutes' => 180,
        ] as $property => $untouched) {
            $component->set($property, '')->assertSet($property, $untouched);
        }

        $component->assertHasNoErrors();
    });

    it('laisse une valeur vide tranquille sur une propriété qui l\'accepte', function (): void {
        // `startTime` est une string : le vide y est une valeur légitime, et le
        // garde-fou ne doit pas s'en mêler.
        wizardAtSetup()
            ->set('startTime', '')
            ->assertSet('startTime', '');
    });
});
