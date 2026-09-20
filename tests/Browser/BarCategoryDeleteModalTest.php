<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\ClubAdmin\Users\Models\User;

/**
 * Page servie en GET par un contrôleur : aucun composant Livewire, donc pas de
 * wire:model pour piloter une confirmation. L'état vit dans Alpine.
 *
 * Trois pièges connus se cumulent ici, et aucun test PHP ne les voit — la vue
 * rend le même HTML que la boîte s'ouvre ou non. D'où cette vérification dans un
 * vrai navigateur : le drawer du layout porte un `transform`, qui piège tout
 * `position: fixed` ; `x-trap` de maryUI attend un `open` dans la portée ; et la
 * boîte doit passer par `showModal()` pour atteindre le top layer.
 */
it('opens the category deletion box, and names what is about to go', function (): void {
    $admin = User::factory()->isAdmin()->create();
    BarCategory::create(['name' => "L'Étoile"]);

    $this->actingAs($admin);

    $page = visit(route('bar.categories.index'))->resize(1280, 900);

    $page->assertNoJavaScriptErrors()
        ->wait(1)
        ->click('[aria-label="Supprimer L\'Étoile"]')
        ->wait(1);

    $probe = $page->script(<<<'JS'
        (() => {
          const dlg = document.querySelector('dialog.modal[open]');
          if (! dlg) return { open: false };

          const box = dlg.querySelector('.modal-box');
          const r = box.getBoundingClientRect();
          const x = Math.round(r.left + r.width / 2);
          const y = Math.round(r.top + r.height / 2);
          const hit = document.elementFromPoint(x, y);

          return {
            open: true,
            // Le piège du transform posait la boîte des milliers de pixels plus bas.
            onScreen: r.top >= 0 && r.bottom <= window.innerHeight,
            reachable: hit !== null && box.contains(hit),
            names: dlg.textContent.includes("L'Étoile"),
            action: dlg.querySelector('[data-confirm-form]')?.getAttribute('action') ?? null,
          };
        })()
    JS);

    $p = $probe[0] ?? $probe;

    expect($p['open'])->toBeTrue('la boîte de confirmation ne s\'ouvre pas');
    expect($p['onScreen'])->toBeTrue('la boîte est posée hors de l\'écran');
    expect($p['reachable'])->toBeTrue('la boîte est à l\'écran mais rien n\'y est cliquable');
    expect($p['names'])->toBeTrue('la boîte doit nommer la catégorie visée');
    expect($p['action'])->toContain('/bar/categories/');
})->group('bar');
