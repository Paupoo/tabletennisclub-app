<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\TrainingPack;

/**
 * Le roster est le premier endroit où <x-admin.shared.row-menu> vit dans un
 * <table>. Les onze autres écrans l'emploient dans des listes ou des cartes,
 * donc aucun n'avait rencontré le conteneur de défilement de la table : son
 * `overflow-x-auto` ouvre un contexte de rognage, et il coupait le panneau.
 *
 * Aucun test Livewire ne voit ça — le markup est identique dans les deux cas.
 */
it('opens the roster row actions without the table clipping them', function (): void {
    $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $season = makeActiveSeason();
    $room = Room::factory()->create(['name' => 'Demeester -1', 'capacity_for_trainings' => 8]);
    $coach = User::factory()->isCoach()->create(['first_name' => 'Eric', 'last_name' => 'Filee']);

    $pack = TrainingPack::factory()->create([
        'name' => 'Mercredi — Initiation jeunes',
        'season_id' => $season->id,
        'room_id' => $room->id,
        'trainer_id' => $coach->id,
        'training_level_id' => trainingLevelId('Débutant'),
        'type' => TrainingType::DIRECTED->value,
        'day_of_week' => 3,
        'start_time' => '15:00:00',
        'duration_minutes' => 90,
        'price' => 90,
        'is_active' => true,
    ]);

    $member = activeMember($season, ['first_name' => 'Camille', 'last_name' => 'Dubois']);
    $subscription = Subscription::where('user_id', $member->id)->where('season_id', $season->id)->firstOrFail();
    $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

    $this->actingAs($admin);

    $page = visit(route('admin.trainings.index'))->resize(1440, 900);

    $page->assertNoJavaScriptErrors()
        ->click('[wire\\:click="openPack(' . $pack->id . ')"]')
        ->assertSee('Dubois Camille')
        // Une vraie attente, pas une assertion : la fiche vient d'être rendue par
        // Livewire, et Alpine n'a pas encore repris la main sur le déclencheur —
        // un clic immédiat ne bascule rien et le panneau reste `display: none`.
        ->wait(1)
        ->click('[data-row-menu-trigger]')
        ->wait(1);

    // Un rectangle suffisait à certifier un panneau invisible : getBoundingClientRect
    // renvoie les mêmes coordonnées qu'il soit rogné ou non. On demande donc au
    // navigateur ce qu'il y a vraiment sous le panneau.
    //
    // Et on vise le **bas**, pas le haut : une première version échantillonnait
    // à 20px du sommet, c'est-à-dire la seule bande qu'un conteneur trop court
    // laisse dépasser. Elle est passée au vert sur un panneau coupé en deux.
    // La dernière entrée du menu est celle qu'il faut pouvoir cliquer.
    $probe = $page->script(<<<'JS'
        (() => {
          const panel = [...document.querySelectorAll('[data-row-menu-panel]')]
            .find((e) => e.getClientRects().length > 0);
          if (!panel) return { found: false };
          const r = panel.getBoundingClientRect();
          const x = Math.round(r.left + r.width / 2);
          const hit = (y) => {
            const e = document.elementFromPoint(x, Math.round(y));
            return e !== null && panel.contains(e);
          };

          // Tout ancêtre qui rogne, quel qu'il soit : c'est la cause, le point
          // manqué n'en est que le symptôme.
          const clippers = [];
          for (let el = panel.parentElement; el && el !== document.documentElement; el = el.parentElement) {
            const cs = getComputedStyle(el);
            if (cs.overflowX !== 'visible' || cs.overflowY !== 'visible') {
              clippers.push(el.tagName.toLowerCase() + '.' + (el.className || '').toString().slice(0, 60));
            }
          }

          return {
            found: true,
            onScreen: r.right <= window.innerWidth && r.bottom <= window.innerHeight && r.top >= 0,
            reachable: hit(r.top + 8) && hit(r.top + r.height / 2) && hit(r.bottom - 8),
            clippers: clippers.filter((c) => ! c.startsWith('body.')),
          };
        })()
    JS);

    $p = $probe[0] ?? $probe;

    expect($p['found'])->toBeTrue('la ligne doit proposer ses actions');
    expect($p['clippers'])->toBe([], 'un ancêtre du panneau rogne : ' . implode(', ', $p['clippers']));
    expect($p['reachable'])->toBeTrue('le panneau est rogné — son bas n\'est pas cliquable');
    expect($p['onScreen'])->toBeTrue('le panneau doit tenir dans la fenêtre');
})->group('training');
