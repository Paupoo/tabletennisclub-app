<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Role;

/**
 * Mary enveloppe une <x-table> dans `overflow-x-auto` (Table.php:266), ce qui
 * ouvre un contexte de rognage et coupe le panneau de <x-admin.shared.row-menu>.
 * Neuf écrans le faisaient. Le conteneur se déclare donc transparent à partir de
 * lg, où la table tient dans sa boîte.
 *
 * tests/Architecture/RowMenuInTableTest.php garde la classe sur les neuf ; ce
 * test-ci vérifie qu'elle produit bien l'effet attendu sur une table de Mary,
 * qui n'est pas le conteneur écrit à la main du roster des entraînements.
 */
it('opens a members-list row menu without the table clipping it', function (): void {
    Club::factory()->ownClub()->create();

    $secretary = User::factory()->withRole(Role::MEMBERS)->create([
        'first_name' => 'Simone',
        'last_name' => 'Abitbol',
    ]);

    User::factory()->count(3)->create();

    $this->actingAs($secretary);

    $page = visit(route('admin.users.index'))->resize(1440, 900);

    $page->assertNoJavaScriptErrors()
        ->wait(1)
        // Les deux jumeaux sont dans le DOM, seul celui du tableau est à l'écran
        // à cette largeur : on vise une ligne précise, sinon le sélecteur est
        // ambigu et le clic échoue.
        ->click('tbody tr:first-child [data-row-menu-trigger]')
        ->wait(1);

    $probe = $page->script(<<<'JS'
        (() => {
          const panel = [...document.querySelectorAll('tbody [data-row-menu-panel]')]
            .find((e) => e.getClientRects().length > 0);
          if (!panel) return { found: false };

          const r = panel.getBoundingClientRect();
          const x = Math.round(r.left + r.width / 2);
          const hit = (y) => {
            const e = document.elementFromPoint(x, Math.round(y));
            return e !== null && panel.contains(e);
          };

          const clippers = [];
          for (let el = panel.parentElement; el && el !== document.documentElement; el = el.parentElement) {
            const cs = getComputedStyle(el);
            if (cs.overflowX !== 'visible' || cs.overflowY !== 'visible') {
              clippers.push(el.tagName.toLowerCase() + '.' + (el.className || '').toString().slice(0, 60));
            }
          }

          return {
            found: true,
            reachable: hit(r.top + 8) && hit(r.top + r.height / 2) && hit(r.bottom - 8),
            clippers: clippers.filter((c) => ! c.startsWith('body.')),
          };
        })()
    JS);

    $p = $probe[0] ?? $probe;

    expect($p['found'])->toBeTrue('la ligne doit proposer ses actions');
    expect($p['clippers'])->toBe([], 'un ancêtre rogne le panneau : ' . implode(', ', $p['clippers']));
    expect($p['reachable'])->toBeTrue('le panneau est rogné — son bas n\'est pas cliquable');
})->group('users');
