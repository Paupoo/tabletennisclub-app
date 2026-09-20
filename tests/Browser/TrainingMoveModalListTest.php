<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;

/**
 * La liste d'un <x-choices-offline> bascule au-dessus du champ quand elle ne
 * tient pas dessous. Dans une modale, elle débordait alors par le haut et s'y
 * faisait rogner : on voyait quatre options coupées, et la première était
 * inatteignable. Un paragraphe posé avant le champ suffisait à provoquer ça —
 * il pousse le champ vers le bas et lui retire la place dont la liste a besoin.
 */
it('opens the destination list inside the modal, not through its top edge', function (): void {
    $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $season = makeActiveSeason();
    Room::factory()->create(['capacity_for_trainings' => 8]);

    $from = makeTrainingPack($season, ['name' => 'Samedi — Initiation jeunes']);

    // Assez de candidats pour que la liste soit longue : avec deux options elle
    // tient partout, et le test ne dirait plus rien.
    foreach (['Lundi — Entrée libre', 'Mardi — Perfectionnement', 'Mercredi — Initiation jeunes', 'Jeudi — Compétition', 'Vendredi — Loisir'] as $name) {
        makeTrainingPack($season, ['name' => $name]);
    }

    $member = activeMember($season, ['first_name' => 'Camille', 'last_name' => 'Dubois']);
    $subscription = Subscription::where('user_id', $member->id)->where('season_id', $season->id)->firstOrFail();
    $subscription->trainingPacks()->attach($from->id, ['status' => 'enrolled']);

    $this->actingAs($admin);

    // 1280×800 : un portable ordinaire, le format où la modale est le plus à
    // l'étroit tout en restant au-dessus du point de bascule `lg`.
    $page = visit(route('admin.trainings.index'))->resize(1280, 800);

    $page->assertNoJavaScriptErrors()
        ->click('[wire\\:click="openPack(' . $from->id . ')"]')->wait(1)
        ->click('[data-row-menu-trigger]')->wait(1)
        ->click('[data-row-menu-primary]')->wait(1)
        ->click('.modal-open label.select')->wait(1);

    $probe = $page->script(<<<'JS'
        (() => {
          const modal = document.querySelector('.modal-open');
          const option = modal?.querySelector('[id^="option-"]');
          if (!option) return { found: false };

          // La boîte de défilement du popover, pas la liste qu'elle contient :
          // une liste plus haute que sa boîte, c'est du défilement, pas un
          // rognage. Une première version mesurait la liste et criait au bug.
          let popover = option.parentElement;
          while (popover && getComputedStyle(popover).position !== 'absolute') {
            popover = popover.parentElement;
          }
          if (! popover) return { found: false };

          const r = popover.getBoundingClientRect();
          if (r.height === 0) return { found: false };

          const box = modal.querySelector('.modal-box').getBoundingClientRect();
          const x = Math.round(r.left + r.width / 2);
          const reaches = (y) => {
            const hit = document.elementFromPoint(x, Math.round(y));
            return hit !== null && popover.contains(hit);
          };

          return {
            found: true,
            insideViewport: r.top >= 0 && r.bottom <= window.innerHeight,
            insideModal: r.top >= box.top && r.bottom <= box.bottom,
            topReachable: reaches(r.top + 6),
            bottomReachable: reaches(r.bottom - 6),
          };
        })()
    JS);

    $p = $probe[0] ?? $probe;

    expect($p['found'])->toBeTrue('la liste des packs de destination doit s\'ouvrir');
    expect($p['insideViewport'])->toBeTrue('la liste sort de la fenêtre');
    expect($p['insideModal'])->toBeTrue('la liste déborde de la modale, qui la rogne');
    expect($p['topReachable'])->toBeTrue('le haut de la liste est rogné — la première option est inatteignable');
    expect($p['bottomReachable'])->toBeTrue('le bas de la liste est rogné');
})->group('training');
