<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarOrderItem;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Role;

/*
|--------------------------------------------------------------------------
| Bar — migration vers le design system du club
|--------------------------------------------------------------------------
|
| Le module Bar rendait ses écrans avec une feuille de style autonome
| (public/assets/bar/bar.css) et un thème sombre à lui. Ces tests verrouillent
| la bascule : les huit écrans rendent, ils passent par la CSS compilée de
| l'application, et la navigation ne montre plus de lien vers un 403.
|
*/

beforeEach(function (): void {
    // Administrateur : c'est aujourd'hui le seul profil qui atteint les huit
    // écrans. Aucun rôle du bar ne porte `bar.cash_sheet.send` — la feuille de
    // caisse est hors de portée d'un barman comme d'un magasinier. C'est une
    // lacune de la matrice des rôles, pas des vues ; le test la constate.
    $this->manager = User::factory()->isAdmin()->create();

    $this->category = BarCategory::create(['name' => 'Bières']);

    // Deux produits au minimum : une fixture à une seule ligne ne déclenche pas
    // les violations de chargement paresseux qu'une vraie liste provoque.
    $this->inStock = BarProduct::create([
        'name' => 'Jupiler 25 cl',
        'sale_price' => 180,
        'is_available' => 1,
        'category_id' => $this->category->id,
    ]);

    $this->outOfStock = BarProduct::create([
        'name' => 'Chimay bleue',
        'sale_price' => 400,
        'is_available' => 1,
        'category_id' => $this->category->id,
    ]);

    BarStockMovement::create([
        'product_id' => $this->inStock->id,
        'quantity' => 24,
        'movement_type' => 'IN',
        'created_by' => $this->manager->id,
    ]);
});

function barScreens(): array
{
    return [
        'commande' => 'bar.index',
        'panier' => 'bar.cart.show',
        'commandes' => 'bar.orders.index',
        'historique' => 'bar.orders.history',
        'produits' => 'bar.products.index',
        'catégories' => 'bar.categories.index',
        'feuille de caisse' => 'bar.cashSheet.index',
    ];
}

it('renders every bar screen', function (string $route): void {
    $this->actingAs($this->manager)
        ->get(route($route))
        ->assertOk();
})->with(barScreens());

it('no longer loads the standalone bar stylesheet', function (string $route): void {
    // bar.css portait un thème sombre qui faisait du Bar le seul écran de
    // l'application à ne pas suivre le design du club. Le fichier est supprimé :
    // un <link> résiduel donnerait un 404 silencieux, pas une erreur de test.
    $this->actingAs($this->manager)
        ->get(route($route))
        ->assertDontSee('assets/bar/bar.css', escape: false);
})->with(barScreens());

it('renders the payment screen through the club layout', function (): void {
    $order = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 180,
        'is_paid' => false,
    ]);

    BarOrderItem::create([
        'order_id' => $order->id,
        'product_id' => $this->inStock->id,
        'quantity' => 1,
        'unit_price' => 180,
        'total_price' => 180,
    ]);

    $this->actingAs($this->manager)
        ->get(route('bar.payment.show', $order))
        ->assertOk()
        ->assertDontSee('assets/bar/bar.css', escape: false)
        ->assertSee('À encaisser', escape: false);
});

it('lists only unpaid orders, each with its cash-out button', function (): void {
    // BarOrderController::index() ne retourne que les commandes ouvertes
    // (`where('is_paid', 0)`). L'écran est donc une file d'encaissement : une
    // commande réglée n'y figure pas, et « Encaisser » ne s'affiche jamais sur
    // une commande déjà payée.
    $paid = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 180,
        'is_paid' => true,
        'payment_method' => 'cash',
    ]);

    $unpaid = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 400,
        'is_paid' => false,
    ]);

    $this->actingAs($this->manager)
        ->get(route('bar.orders.index'))
        ->assertOk()
        ->assertSee('#' . $unpaid->id)
        ->assertSee(route('bar.payment.show', $unpaid), escape: false)
        ->assertDontSee(route('bar.payment.show', $paid), escape: false);
});

it('hides the nav entries a barman has no permission for', function (): void {
    // routes/bar.php verrouille produits, catégories et feuille de caisse.
    // Sans filtrage du menu, un barman voyait trois liens menant à un 403.
    $barman = User::factory()->create();
    $barman->assignRole(Role::BARMAN->value);

    $response = $this->actingAs($barman)
        ->get(route('bar.index'))
        ->assertOk();

    $response->assertSee(route('bar.orders.index'), escape: false);
    $response->assertDontSee(route('bar.products.index'), escape: false);
    $response->assertDontSee(route('bar.categories.index'), escape: false);
    $response->assertDontSee(route('bar.cashSheet.index'), escape: false);
});

it('shows the stock state of each product on the order screen', function (): void {
    $this->actingAs($this->manager)
        ->get(route('bar.index'))
        ->assertOk()
        ->assertSee('24 en stock')
        ->assertSee('Rupture de stock');
});

it('serves the bar through the application stylesheet built by vite', function (): void {
    // Cœur de la bascule : le Bar ne charge plus une CSS à lui, il prend la même
    // que le reste du back-office. Sans cette assertion, un retour au <link>
    // autonome passerait tous les autres tests.
    $this->actingAs($this->manager)
        ->get(route('bar.index'))
        ->assertOk()
        ->assertSee('build/assets/app-', escape: false);
});

it('keeps the delete confirmation wired on a product card', function (): void {
    // <x-card> fusionne les attributs de l'appelant sur sa div racine. Si Mary
    // cessait de le faire, le x-data disparaîtrait et la confirmation de
    // suppression ne s'ouvrirait plus — sans la moindre erreur.
    $this->actingAs($this->manager)
        ->get(route('bar.products.index'))
        ->assertOk()
        ->assertSee('confirming: false', escape: false);
});

/*
|--------------------------------------------------------------------------
| Intégration au back-office
|--------------------------------------------------------------------------
|
| Le Bar a quitté son layout dédié pour <x-app-layout>. Ses six écrans sont
| devenus un groupe de la barre latérale, et deux motifs du design system ont
| remplacé les commandes de l'ancien en-tête : row-menu pour les actions d'une
| commande, pilule flottante pour le panier.
|
*/

it('renders the bar inside the back-office shell, not a layout of its own', function (): void {
    $this->actingAs($this->manager)
        ->get(route('bar.index'))
        ->assertOk()
        // Le fil d'Ariane n'existe que dans layouts/app.blade.php.
        ->assertSee('breadcrumb-trail', escape: false)
        ->assertSee('Nouvelle commande');
});

it('puts the bar in the main menu for whoever may enter it', function (): void {
    // Le menu est rendu sur tout le back-office : il doit s'ouvrir depuis
    // n'importe quelle page, pas seulement depuis le Bar.
    $this->actingAs($this->manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('bar.index'), escape: false)
        ->assertSee(route('bar.orders.history'), escape: false);
});

it('keeps the bar out of the main menu for someone without access', function (): void {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('bar.index'), escape: false)
        ->assertDontSee(route('bar.cashSheet.index'), escape: false);
});

it('badges the menu entry with the number of items in the cart', function (): void {
    // La pastille se lit depuis n'importe quel écran : c'est elle qui signale
    // qu'un panier est resté ouvert.
    // On compare les deux rendus plutôt que de chercher « badge-primary », que
    // huit autres composants emploient : seule la différence prouve la pastille.
    $without = $this->actingAs($this->manager)
        ->get(route('dashboard'))->assertOk()->getContent();

    $with = $this->actingAs($this->manager)
        ->withSession(['cart' => [$this->inStock->id => 3]])
        ->get(route('dashboard'))->assertOk()->getContent();

    expect(substr_count((string) $with, 'badge-primary'))
        ->toBeGreaterThan(substr_count((string) $without, 'badge-primary'));
});

it('floats the cart pill only once the cart holds something', function (): void {
    $empty = $this->actingAs($this->manager)->get(route('bar.index'))->assertOk();
    expect($empty->getContent())->not->toContain('Voir la commande');

    $this->actingAs($this->manager)
        ->withSession(['cart' => [$this->inStock->id => 2]])
        ->get(route('bar.index'))
        ->assertOk()
        ->assertSee('Voir la commande')
        // Le total prouve que $totalPrice arrive jusqu'à la pilule : 2 × 1,80 €.
        ->assertSee(euros(360));
});

it('puts the secondary order actions behind a named menu', function (): void {
    // Option B : « Encaisser » en ligne, le reste derrière « Plus ». Le bouton
    // porte un nom — un ⋮ nu n'est annoncé par aucun lecteur d'écran.
    $order = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 180,
        'is_paid' => false,
    ]);

    $response = $this->actingAs($this->manager)
        ->get(route('bar.orders.index'))
        ->assertOk();

    $response->assertSee('Encaisser');
    $response->assertSee('Modifier la commande');
    $response->assertSee('Supprimer la commande');
    $response->assertSee('data-row-menu-trigger', escape: false);
    $response->assertSee(route('bar.payment.show', $order), escape: false);
});

it('draws order cards without a coloured side accent', function (): void {
    // Le statut de paiement est déjà écrit en toutes lettres sur sa pastille ;
    // un liseré coloré en plus bariolait la pile de commandes.
    BarOrder::create([
        'created_by' => $this->manager->id, 'total_price' => 180, 'is_paid' => false,
    ]);

    $this->actingAs($this->manager)
        ->get(route('bar.orders.index'))
        ->assertOk()
        ->assertDontSee('border-s-', escape: false);
});

it('opens the QR code in a modal instead of the page flow', function (): void {
    // En pleine page, le QR poussait « Paiement reçu » sous la ligne de
    // flottaison : il fallait faire défiler pendant que le client attend.
    Club::factory()->ownClub()->create();
    Club::forgetOwnClub();

    $order = BarOrder::create([
        'created_by' => $this->manager->id, 'total_price' => 180, 'is_paid' => false,
    ]);

    // Afficher le QR est une lecture : c'est un lien, pas un POST. La réponse
    // GET est aussi la seule à recevoir les scripts de l'application — sans eux
    // Alpine n'existe pas et la modale ne s'ouvre jamais.
    $response = $this->actingAs($this->manager)
        ->get(route('bar.payment.show', ['order' => $order, 'method' => 'qr']))
        ->assertOk();

    $response->assertSee('id="bar-qr-modal"', escape: false);
    $response->assertSee('Paiement reçu');

    // showModal() et non l'attribut `open` : le drawer du layout porte un
    // transform, qui devient le bloc conteneur de tout descendant en
    // position:fixed. Ouverte autrement, la boîte se posait 5 400 px plus bas.
    // Le top layer échappe à ce transform.
    // maryUI émet x-trap="open" sur toute modale : cet état vient normalement
    // d'un wire:model, absent ici, donc Alpine le fournit. Et showModal() plutôt
    // que l'attribut `open` — le drawer du layout porte un transform, qui
    // deviendrait le bloc conteneur de cette boîte en position:fixed.
    $response->assertSee('x-data="{ open: true }"', escape: false);
    $response->assertSee('x-init="$el.showModal()"', escape: false);
});
