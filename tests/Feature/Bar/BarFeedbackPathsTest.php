<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\Bar\Services\CashSheetService;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Bar — les chemins où quelque chose se passe mal
|--------------------------------------------------------------------------
|
| Les écrans du bar rendaient tous correctement ; ce qui ne marchait pas, c'est
| ce qui arrive quand l'action échoue. Trois défauts, tous invisibles :
|
| - une validation de commande en échec partait sur un RouteNotFoundException,
|   parce que le contrôleur redirigeait vers `bar.carts.show` — un nom de route
|   qui n'existe pas. L'erreur métier devenait une page 500, et le panier était
|   perdu ;
| - l'échec d'envoi de la feuille de caisse était flashé en `warning`, un niveau
|   que le pont flash→toast du layout ne relayait pas. Le barman rangeait la
|   caisse en croyant le trésorier servi ;
| - deux routes pointaient vers du vide, dont une vers une méthode inexistante.
|
| Le pont flash→toast lui-même est couvert par tests/Feature/Shared/FlashToastBridgeTest.php :
| il sert toute l'application, ce n'est pas au bar de le garder.
|
| Ces tests verrouillent les trois. Ils visent les chemins d'échec en priorité :
| ce sont ceux qu'aucun clic de recette n'emprunte.
|
*/

/** Ouvre une ardoise, y sert une bière, et l'enregistre — le vrai parcours. */
function openTabAndServe(User $user, string $name, int $productId): void
{
    Livewire::actingAs($user)->test('pages::bar.counter')
        ->set('tabNameInput', $name)
        ->call('openTab')
        ->call('add', $productId);

    Livewire::actingAs($user)->test('pages::bar.cart')->call('validateOrder', 'validate');
}

beforeEach(function (): void {
    $this->manager = User::factory()->isAdmin()->create();

    $this->category = BarCategory::create(['name' => 'Bières']);

    $this->product = BarProduct::create([
        'name' => 'Jupiler 25 cl',
        'sale_price' => 180,
        'is_available' => 1,
        'category_id' => $this->category->id,
    ]);

    // `remaining_quantity` est ce que StockService::consumeFIFO consomme : un lot
    // d'entrée sans lui est invisible au FIFO, et toute validation de commande
    // échoue sur « Stock insuffisant pour appliquer la sortie FIFO » — même avec
    // du stock au compteur. Les fixtures du bar l'omettaient, si bien qu'aucun
    // test n'avait jamais mené une commande jusqu'au bout.
    BarStockMovement::create([
        'product_id' => $this->product->id,
        'quantity' => 2,
        'remaining_quantity' => 2,
        'movement_type' => 'IN',
        'created_by' => $this->manager->id,
    ]);
});

it('says why a checkout failed instead of losing the order', function (): void {
    // Trois au ticket pour deux en stock : validateProductStock lève une
    // RuntimeException. Ce chemin partait autrefois sur un RouteNotFoundException,
    // donc sur une page 500, et le panier — trente taps de travail — n'avait plus
    // de chemin visible pour revenir.
    session()->put(['cart' => [$this->product->id => 3], 'bar_tab_name' => 'Alpa A']);

    Livewire::actingAs($this->manager)
        ->test('pages::bar.cart')
        ->call('validateOrder', 'validate')
        ->assertNoRedirect();

    expect(session('cart'))->toBe([$this->product->id => 3])
        ->and(BarOrder::query()->count())->toBe(0);
});

it('reaches the cart screen under the name the controller redirects to', function (): void {
    // Le défaut n'était pas une faute de frappe visible : `bar.carts.show` et
    // `bar.cart.show` se lisent pareil. Le test nomme la route attendue.
    expect(Route::has('bar.cart.show'))->toBeTrue()
        ->and(Route::has('bar.carts.show'))->toBeFalse();
});

it('says so out loud when the cash sheet cannot be sent', function (): void {
    $this->partialMock(
        CashSheetService::class,
        fn ($mock) => $mock->shouldReceive('sendCsv')->once()->andReturn(false)
    );

    // Une commande du jour, sinon le garde `orders_total === 0` sort avant l'envoi
    // et on ne mesurerait pas le chemin d'échec du mailer.
    openTabAndServe($this->manager, 'Alpa A', $this->product->id);

    $this->actingAs($this->manager)
        ->post(route('bar.cashSheet.send'), [
            'date' => now()->toDateString(),
            'to' => 'tresorier@example.test',
        ])
        ->assertSessionHas('error');
});

it('says so out loud when there is nothing to send', function (): void {
    // Le garde portait sur `empty($csv)`, et buildCsv() écrit toujours sa ligne
    // d'en-tête : il ne se déclenchait donc jamais. Une journée sans vente partait
    // au trésorier sous la forme d'un CSV à zéro ligne, annoncée « Email envoyé
    // avec succès ». Le garde compte maintenant les commandes.
    $this->actingAs($this->manager)
        ->post(route('bar.cashSheet.send'), [
            'date' => now()->subMonth()->toDateString(),
            'to' => 'tresorier@example.test',
        ])
        ->assertSessionHas('error');
});

it('no longer registers a route towards a method that does not exist', function (): void {
    // POST bar/cart/pay visait BarCartController@pay, qui n'a jamais existé :
    // 500 garanti si quelqu'un l'atteignait. bar.payment.show.post était un
    // vestige d'avant le passage du QR en GET. Les routes d'écriture du panier ont
    // suivi quand le ticket est devenu un composant.
    expect(Route::has('bar.cart.pay'))->toBeFalse()
        ->and(Route::has('bar.payment.show.post'))->toBeFalse()
        ->and(Route::has('bar.cart.add'))->toBeFalse()
        ->and(Route::has('bar.cart.validate'))->toBeFalse()
        // Celle-ci reste : c'est l'écran du ticket, et le nom vers lequel un échec
        // de validation renvoyait avec une faute de frappe.
        ->and(Route::has('bar.cart.show'))->toBeTrue();
});

it('exposes the cash sheet send endpoint under a path that does not repeat itself', function (): void {
    // Le chemin était bar/cashsheet/bar/cashSheet/send : le préfixe du groupe,
    // puis le même préfixe écrit à la main dans la route.
    expect(route('bar.cashSheet.send', absolute: false))->toBe('/bar/cashsheet/send');
});

it('offers the cash-out action on every order of the queue', function (): void {
    // La file ne contient que des commandes impayées (BarOrderController::index
    // filtre is_paid = 0), donc la branche « Payé » des cartes ne pouvait pas
    // s'afficher. Elle affirmait pourtant au lecteur que la file les mélange.
    openTabAndServe($this->manager, 'Alpa A', $this->product->id);

    $this->actingAs($this->manager)
        ->get(route('bar.orders.index'))
        ->assertOk()
        ->assertSee('Non payé')
        ->assertDontSee('>Payé<', escape: false);
});
