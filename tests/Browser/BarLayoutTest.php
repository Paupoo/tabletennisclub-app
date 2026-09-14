<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarOrderItem;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;

/**
 * Le Bar est né comme une application autonome en pleine largeur. Repris dans
 * la coquille du back-office, ses écrans ont hérité des 1090 px de la zone de
 * contenu, qui conviennent à un tableau et pas à un ticket : sur l'écran de
 * paiement, 800 px séparaient le nom d'un produit de son prix, et le compteur
 * de quantité fuyait à l'autre bout de la ligne.
 *
 * Ces mesures portent sur la boîte peinte, pas sur le balisage : une classe
 * `max-w-*` présente dans le HTML ne prouve pas que la mesure est bornée.
 */

/** Mesure la largeur des cartes et le débordement horizontal de la page. */
const BAR_LAYOUT = <<<'JS_WRAP'
(() => {
  const doc = document.documentElement;

  const cards = [...document.querySelectorAll('main .card, main [class*="rounded-xl"]')]
    .filter(el => el.getClientRects().length > 0)
    .map(el => Math.round(el.getBoundingClientRect().width));

  return JSON.stringify({
    overflow: Math.round(doc.scrollWidth - doc.clientWidth),
    widest: cards.length ? Math.max(...cards) : 0,
    seen: cards.length,
  });
})()
JS_WRAP;

/** Cibles tactiles du compteur de quantité. */
const BAR_STEPPERS = <<<'JS_WRAP'
(() => {
  const all = [...document.querySelectorAll('main button[aria-label]')]
    .filter(el => el.getClientRects().length > 0);

  const small = all
    .map(el => el.getBoundingClientRect())
    .filter(r => r.width < 44 || r.height < 44)
    .map(r => Math.round(r.width) + 'x' + Math.round(r.height));

  return JSON.stringify({small, seen: all.length});
})()
JS_WRAP;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
    makeActiveSeason();
    $this->actingAs($this->admin);

    $category = BarCategory::create(['name' => 'Bières']);

    $this->products = collect([['Jupiler 25 cl', 180], ['Duvel 33 cl', 350], ['Café', 400]])
        ->map(fn (array $p): BarProduct => BarProduct::create([
            'name' => $p[0], 'sale_price' => $p[1], 'is_available' => 1, 'category_id' => $category->id,
        ]));

    foreach ($this->products as $product) {
        BarStockMovement::create([
            'product_id' => $product->id, 'quantity' => 24,
            'movement_type' => 'IN', 'created_by' => $this->admin->id,
        ]);
    }

    $this->order = BarOrder::create([
        'created_by' => $this->admin->id, 'total_price' => 530, 'is_paid' => false,
    ]);

    foreach ($this->products->take(2) as $product) {
        BarOrderItem::create([
            'order_id' => $this->order->id, 'product_id' => $product->id, 'quantity' => 1,
            'unit_price' => $product->sale_price, 'total_price' => $product->sale_price,
        ]);
    }
});

it('keeps the receipt to a readable measure on a wide screen', function (): void {
    // Le ticket et le panier sont bornés à max-w-2xl (672 px). Sans borne, ils
    // occupaient les 1090 px de la zone de contenu.
    $layout = json_decode(
        (string) visit(route('bar.payment.show', $this->order))->resize(1440, 900)->script(BAR_LAYOUT),
        true,
    );

    expect($layout['seen'])->toBeGreaterThan(0, 'aucune carte mesurée : le sélecteur ne trouve plus rien');
    expect($layout['widest'])->toBeLessThanOrEqual(700, 'un reçu étalé sépare le produit de son prix');
});

it('never scrolls sideways on a phone', function (string $route): void {
    // Les huit écrans, pas six : la feuille de caisse et l'écran de paiement
    // manquaient au jeu de données, et ce sont les deux qui portent le plus de
    // chiffres côte à côte — donc les deux qui débordent le plus volontiers.
    $url = $route === 'bar.payment.show'
        ? route($route, $this->order)
        : route($route);

    $layout = json_decode(
        (string) visit($url)->resize(390, 844)->script(BAR_LAYOUT),
        true,
    );

    expect($layout['overflow'])->toBeLessThanOrEqual(0, "{$route} déborde horizontalement");
})->with([
    'commande' => 'bar.index',
    'panier' => 'bar.cart.show',
    'à encaisser' => 'bar.orders.index',
    'historique' => 'bar.orders.history',
    'produits' => 'bar.products.index',
    'catégories' => 'bar.categories.index',
    'feuille de caisse' => 'bar.cashSheet.index',
    'paiement' => 'bar.payment.show',
]);

/**
 * Défilement latéral confiné dans un conteneur, et cibles tactiles.
 *
 * Le débordement de la page ne suffit pas à décrire l'écran de stock : `<x-table>`
 * enveloppe sa table dans un `overflow-x-auto`, si bien qu'un tableau trop large ne
 * pousse rien — il se défile en silence à l'intérieur de sa carte. C'est ce qui
 * rendait l'inventaire pénible sur téléphone sans qu'aucune sonde ne le voie.
 */
const BAR_TABLE_BOX = <<<'JS_WRAP'
(() => {
  const table = document.querySelector('main table');
  if (! table) return JSON.stringify({seen: 0});

  const box = table.parentElement;
  const sel = 'a[href], button, input:not([type=hidden]), select, textarea, [role=button], summary';

  const small = [...document.querySelectorAll('main ' + sel)]
    .filter(el => {
      const cs = getComputedStyle(el);
      return cs.visibility !== 'hidden' && cs.display !== 'none' && el.getBoundingClientRect().width >= 1;
    })
    .map(el => el.getBoundingClientRect())
    .filter(r => Math.min(Math.round(r.width), Math.round(r.height)) < 24)
    .map(r => Math.round(r.width) + 'x' + Math.round(r.height));

  return JSON.stringify({
    seen: 1,
    sideways: box.scrollWidth - box.clientWidth,
    tableWidth: Math.round(table.getBoundingClientRect().width),
    boxWidth: box.clientWidth,
    small,
  });
})()
JS_WRAP;

it('fits the stock table inside its card on a phone, without sideways scrolling', function (): void {
    // Mesuré avant correction : la table réclamait 376 px pour une boîte de 308, soit
    // 68 px de défilement latéral à chaque ligne lue. 82 px des 390 de l'écran
    // partaient en rembourrage — celui de la zone de contenu plus celui de la carte —
    // et l'en-tête « Disponible » réservait 106 px pour une bascule de 40.
    $box = json_decode(
        (string) visit(route('bar.products.index'))->resize(390, 844)->script(BAR_TABLE_BOX),
        true,
    );

    expect($box['seen'])->toBe(1, 'aucune table mesurée : le sélecteur ne trouve plus rien');
    expect($box['sideways'])->toBeLessThanOrEqual(0, sprintf(
        'le tableau de stock déborde de sa carte : %d px pour %d px disponibles',
        $box['tableWidth'] ?? 0,
        $box['boxWidth'] ?? 0,
    ));
});

it('keeps every control of the stock table above the 24px floor', function (): void {
    // WCAG 2.5.8. `toggle-sm` mesurait 33×20 : la zone cliquable du label l'englobait
    // bien, mais la sonde du projet mesure le contrôle lui-même, et un dessin de 20 px
    // de haut se rate au doigt quoi qu'en dise son enveloppe.
    $box = json_decode(
        (string) visit(route('bar.products.index'))->resize(390, 844)->script(BAR_TABLE_BOX),
        true,
    );

    expect($box['small'])->toBe([], sprintf(
        "Contrôles sous le plancher de 24×24 :\n%s",
        implode("\n", $box['small'] ?? []),
    ));
});

it('keeps every quantity control under the thumb', function (): void {
    // Une ardoise en session : depuis que le comptoir demande d'abord pour qui on
    // sert, /bar montre l'écran de choix tant qu'aucune n'est ouverte — et il n'y a
    // alors aucun compteur à mesurer. C'est l'assertion sur `seen` qui l'a dit, et
    // c'est pour ça qu'elle est là.
    session()->put('bar_tab_name', 'Alpa A');

    // Les « + » et « − » sont les commandes les plus utilisées du point de
    // vente, et les seules manipulées d'une main debout derrière un comptoir.
    //
    // Le sélecteur vise `main button[aria-label]` et non `main form button[…]` :
    // depuis le passage en Livewire, les compteurs sont des `wire:click` et ne
    // vivent plus dans un formulaire. L'ancien sélecteur ne trouverait plus rien —
    // et la sonde passerait à vide, ce que l'assertion sur `seen` interdit.
    $steppers = json_decode(
        (string) visit(route('bar.index'))->resize(390, 844)->script(BAR_STEPPERS),
        true,
    );

    expect($steppers['seen'])->toBeGreaterThan(0, 'aucun compteur mesuré : le sélecteur ne trouve plus rien');
    expect($steppers['small'])->toBe([], 'un compteur sous 44 px se rate au doigt');
});

it('shows the whole QR modal without scrolling on a phone', function (): void {
    // La raison d'être de la modale : le code, le montant et « Paiement reçu »
    // tiennent ensemble dans l'écran, pendant que le client attend son tour.
    Club::factory()->ownClub()->create();
    Club::forgetOwnClub();

    $box = json_decode(
        (string) visit(route('bar.payment.show', ['order' => $this->order, 'method' => 'qr']))
            ->resize(390, 844)
            ->script(<<<'JS_WRAP'
            (() => {
              const dialog = document.getElementById('bar-qr-modal');
              const confirm = [...document.querySelectorAll('#bar-qr-modal button')]
                .find(b => b.textContent.trim().includes('Paiement reçu'));
            
              if (!dialog || !confirm) return JSON.stringify({found: false});
            
              const r = confirm.getBoundingClientRect();
            
              return JSON.stringify({
                found: true,
                open: dialog.open,
                inView: r.top >= 0 && r.bottom <= window.innerHeight,
              });
            })()
            JS_WRAP),
        true,
    );

    expect($box['found'])->toBeTrue('la modale ou son bouton de confirmation est introuvable');
    expect($box['open'])->toBeTrue('la modale ne s\'ouvre pas');
    expect($box['inView'])->toBeTrue('« Paiement reçu » est hors de l\'écran : il faut encore faire défiler');
});
