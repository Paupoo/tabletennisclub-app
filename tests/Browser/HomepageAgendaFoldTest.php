<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;

/*
 * Le pli de l'agenda est du comportement, pas du balisage : un test HTTP voit
 * bien les quatorze jours dans la réponse, mais ne peut pas dire lesquels sont
 * réellement à l'écran.
 *
 * Deux pièges qu'il garde :
 *
 * - la deuxième semaine doit être masquée par une classe présente dans le HTML
 *   servi, pas posée par Alpine après coup — sinon elle s'affiche en clair
 *   pendant le démarrage, et le pli ne sert plus à rien ;
 * - à partir de `lg` il n'y a pas de pli du tout : les quatorze jours sont
 *   dépliés et le bouton disparaît.
 */

const AGENDA_MEASURE = <<<'JS'
(() => {
  const visible = el => el.getBoundingClientRect().height > 0;
  const fold = document.querySelector('[data-agenda-folded]');
  const button = [...document.querySelectorAll('button')]
    .find(b => b.textContent.trim().startsWith('Voir les jours suivants')
            || b.textContent.trim().startsWith('Masquer les jours suivants'));

  return {
    jours_visibles: [...document.querySelectorAll('[data-agenda-day]')].filter(visible).length,
    jours_total: document.querySelectorAll('[data-agenda-day]').length,
    seconde_semaine_visible: fold ? visible(fold) : null,
    bouton_visible: button ? visible(button) : false,
  };
})()
JS;

beforeEach(function (): void {
    Club::factory()->ownClub()->create();

    $season = Season::factory()->create([
        'is_active' => true,
        'start_at' => now()->subMonths(2),
        'end_at' => now()->addMonths(9),
    ]);

    $room = Room::factory()->create(['name' => 'Demeester -1']);

    // Deux packs en alternance : aucun n'aligne trois jours d'affilée, donc
    // rien n'est replié en stage et chaque jour reste une ligne distincte.
    $packs = collect([2, 4])->map(fn (int $dayOfWeek): TrainingPack => TrainingPack::factory()->create([
        'season_id' => $season->id, 'room_id' => $room->id, 'is_active' => true,
        'name' => "Pack {$dayOfWeek}", 'day_of_week' => $dayOfWeek,
        'start_time' => '20:30:00', 'duration_minutes' => 90,
    ]));

    foreach (range(1, 12) as $offset) {
        $start = now()->addDays($offset)->setTime(20, 30);

        Training::factory()->create([
            'season_id' => $season->id,
            'room_id' => $room->id,
            'training_pack_id' => $packs[$offset % 2]->id,
            'start' => $start,
            'end' => $start->copy()->addMinutes(90),
        ]);
    }
});

it('hides the second week on a phone until the reader asks for it', function (): void {
    $page = visit('/')->resize(390, 844);

    $atRest = (array) $page->script(AGENDA_MEASURE);

    expect($atRest['jours_total'])->toBe(12)
        ->and($atRest['seconde_semaine_visible'])->toBeFalse(
            'la deuxième semaine doit être masquée par le HTML servi, pas après coup')
        ->and($atRest['jours_visibles'])->toBeLessThan(12)
        ->and($atRest['bouton_visible'])->toBeTrue();

    $page->click('Voir les jours suivants');

    $expanded = (array) $page->script(AGENDA_MEASURE);

    expect($expanded['jours_visibles'])->toBe(12)
        ->and($expanded['seconde_semaine_visible'])->toBeTrue();
});

it('unfolds the whole fortnight on a desktop and offers no button', function (): void {
    $page = (array) visit('/')->resize(1440, 900)->script(AGENDA_MEASURE);

    expect($page['jours_visibles'])->toBe(12)
        ->and($page['seconde_semaine_visible'])->toBeTrue()
        ->and($page['bouton_visible'])->toBeFalse();
});
