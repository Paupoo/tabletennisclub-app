<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Users\Models\User;

/**
 * Même mécanique que la suppression d'une catégorie, avec un piège de plus : le
 * bouton vit dans <x-admin.shared.row-menu>, qui déclare son propre `open`. Un
 * état de page du même nom serait masqué là, et le clic ouvrirait le menu plutôt
 * que la boîte — d'où l'événement.
 */
it('opens the order deletion box from inside the row menu', function (): void {
    $admin = User::factory()->isAdmin()->create();

    $order = BarOrder::create([
        'created_by' => $admin->id,
        'name' => 'Table du fond',
        'total_price' => 180,
        'is_paid' => false,
    ]);

    $this->actingAs($admin);

    $page = visit(route('bar.orders.index'))->resize(1280, 900);

    $page->assertNoJavaScriptErrors()
        ->wait(1)
        ->click('[data-row-menu-trigger]')
        ->wait(1)
        ->click('[data-delete-order]')
        ->wait(1);

    $probe = $page->script(<<<'JS'
        (() => {
          const dlg = document.querySelector('dialog.modal[open]');
          if (! dlg) return { open: false };

          const box = dlg.querySelector('.modal-box');
          const r = box.getBoundingClientRect();
          const hit = document.elementFromPoint(
            Math.round(r.left + r.width / 2),
            Math.round(r.top + r.height / 2),
          );

          return {
            open: true,
            onScreen: r.top >= 0 && r.bottom <= window.innerHeight,
            reachable: hit !== null && box.contains(hit),
            names: dlg.textContent.includes('Table du fond'),
            action: dlg.querySelector('[data-confirm-form]')?.getAttribute('action') ?? null,
          };
        })()
    JS);

    $p = $probe[0] ?? $probe;

    expect($p['open'])->toBeTrue('la boîte de confirmation ne s\'ouvre pas');
    expect($p['onScreen'])->toBeTrue('la boîte est posée hors de l\'écran');
    expect($p['reachable'])->toBeTrue('la boîte est à l\'écran mais rien n\'y est cliquable');
    expect($p['names'])->toBeTrue('la boîte doit nommer la commande visée');
    expect($p['action'])->toContain('/bar/orders/' . $order->id);
})->group('bar');
