<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Bar — le tableau de stock (issue #124)
|--------------------------------------------------------------------------
|
| L'écran produits était une tuile par produit : deux champs, un toggle, deux
| boutons, ~180 px de haut. Faire l'inventaire d'une quarantaine de références
| demandait autant de défilement qu'il y a d'étagères, et deux nombres voisins
| n'étaient jamais à l'écran ensemble.
|
| Ce qui est verrouillé ici :
|
| - le champ porte un COMPTAGE, pas un mouvement : l'écart devient une entrée ou
|   une sortie FIFO, et un compte juste n'écrit rien ;
| - un champ vidé est ignoré — on ne compte pas « rien », et le lire comme 0
|   viderait le rayon par accident ;
| - le seuil d'alerte est par produit, avec repli sur le défaut du bar : le « <= 3 »
|   écrit en dur dans deux vues ne décidait pour aucune ;
| - le stock se charge en un nombre borné de requêtes, quel que soit le nombre de
|   produits. Sans ça l'écran ne tient pas : getStockAttribute fait deux SUM par
|   lecture, soit ~80 requêtes pour 40 produits.
|
*/

beforeEach(function (): void {
    $this->manager = User::factory()->isAdmin()->create();

    $this->beers = BarCategory::create(['name' => 'Bières']);
    $this->softs = BarCategory::create(['name' => 'Softs']);

    $this->jupiler = stockedProduct('Jupiler 25 cl', 200, $this->beers->id, 24, $this->manager->id);
    $this->coca = stockedProduct('Coca-Cola', 150, $this->softs->id, 2, $this->manager->id);
});

/**
 * Un produit avec du stock réellement consommable.
 *
 * `remaining_quantity` est ce que StockService::consumeFIFO cherche : un lot
 * d'entrée sans lui est invisible au FIFO, et toute sortie échoue sur « Stock
 * insuffisant » alors que le compteur affiche du stock.
 */
function stockedProduct(string $name, int $cents, int $categoryId, int $quantity, int $userId): BarProduct
{
    $product = BarProduct::create([
        'name' => $name,
        'sale_price' => $cents,
        'is_available' => 1,
        'category_id' => $categoryId,
    ]);

    if ($quantity > 0) {
        BarStockMovement::create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'movement_type' => BarStockMovement::TYPE_IN,
            'created_by' => $userId,
        ]);
    }

    return $product;
}

it('renders the stock screen', function (): void {
    $this->actingAs($this->manager)
        ->get(route('bar.products.index'))
        ->assertOk()
        ->assertSee('Jupiler 25 cl')
        ->assertSee('Bières');
});

it('filters products by name', function (): void {
    Livewire::actingAs($this->manager)
        ->test('pages::bar.products')
        ->set('search', 'jupiler')
        ->assertSee('Jupiler 25 cl')
        ->assertDontSee('Coca-Cola');
});

it('turns a count above the shelf into an incoming movement of the difference', function (): void {
    Livewire::actingAs($this->manager)
        ->test('pages::bar.products')
        ->call('updateStock', $this->jupiler->id, '30');

    expect($this->jupiler->fresh()->stock)->toBe(30);

    $movement = BarStockMovement::query()
        ->where('product_id', $this->jupiler->id)
        ->where('reason', 'Inventory count')
        ->sole();

    expect($movement->movement_type)->toBe(BarStockMovement::TYPE_IN)
        ->and((int) $movement->quantity)->toBe(6);
});

it('turns a count below the shelf into a consumption of the difference', function (): void {
    Livewire::actingAs($this->manager)
        ->test('pages::bar.products')
        ->call('updateStock', $this->jupiler->id, '20');

    expect($this->jupiler->fresh()->stock)->toBe(20)
        ->and(BarStockMovement::query()
            ->where('product_id', $this->jupiler->id)
            ->where('movement_type', BarStockMovement::TYPE_OUT)
            ->sum('quantity'))->toBe(4);
});

it('writes nothing when the count is already right', function (): void {
    // Un inventaire où trente produits sur quarante tombent juste ne doit pas
    // écrire trente mouvements nuls : l'historique deviendrait illisible.
    $before = BarStockMovement::query()->count();

    Livewire::actingAs($this->manager)
        ->test('pages::bar.products')
        ->call('updateStock', $this->jupiler->id, '24');

    expect(BarStockMovement::query()->count())->toBe($before)
        ->and($this->jupiler->fresh()->stock)->toBe(24);
});

it('ignores an emptied count instead of reading it as zero', function (): void {
    // On ne compte pas « rien ». Lire un champ vidé comme 0 viderait le rayon
    // parce que quelqu'un a effacé avant de se raviser.
    Livewire::actingAs($this->manager)
        ->test('pages::bar.products')
        ->call('updateStock', $this->jupiler->id, '');

    expect($this->jupiler->fresh()->stock)->toBe(24);
});

it('refuses a negative count', function (): void {
    Livewire::actingAs($this->manager)
        ->test('pages::bar.products')
        ->call('updateStock', $this->jupiler->id, '-5');

    expect($this->jupiler->fresh()->stock)->toBe(24);
});

it('flags low stock against the product threshold, not a number written in a view', function (): void {
    // Un fût à 3 est en rupture, un paquet de chips à 3 va très bien : c'était la
    // raison de sortir le « <= 3 » des vues.
    $this->jupiler->update(['low_stock_threshold' => 30]);

    expect($this->jupiler->fresh()->is_low_stock)->toBeTrue()
        ->and($this->coca->fresh()->is_low_stock)->toBeTrue();

    $this->coca->update(['low_stock_threshold' => 1]);

    expect($this->coca->fresh()->is_low_stock)->toBeFalse();
});

it('falls back to the bar default when a product declares no threshold', function (): void {
    expect($this->jupiler->low_stock_threshold)->toBeNull()
        ->and($this->jupiler->effective_low_stock_threshold)->toBe(BarProduct::LOW_STOCK_THRESHOLD);

    Livewire::actingAs($this->manager)
        ->test('pages::bar.products')
        ->call('updateThreshold', $this->jupiler->id, '12');

    expect($this->jupiler->fresh()->effective_low_stock_threshold)->toBe(12);

    Livewire::actingAs($this->manager)
        ->test('pages::bar.products')
        ->call('updateThreshold', $this->jupiler->id, '');

    expect($this->jupiler->fresh()->low_stock_threshold)->toBeNull()
        ->and($this->jupiler->fresh()->effective_low_stock_threshold)->toBe(BarProduct::LOW_STOCK_THRESHOLD);
});

it('takes a product off the menu without touching its stock', function (): void {
    Livewire::actingAs($this->manager)
        ->test('pages::bar.products')
        ->call('updateAvailability', $this->jupiler->id, false);

    expect((int) $this->jupiler->fresh()->is_available)->toBe(0)
        ->and($this->jupiler->fresh()->stock)->toBe(24);
});

it('orders by urgency, most critical first', function (): void {
    // Coca est à 2 pour un seuil de 3 ; Jupiler à 24 pour le même seuil.
    $groups = Livewire::actingAs($this->manager)
        ->test('pages::bar.products', ['order' => 'criticality'])
        ->viewData('groups');

    expect($groups)->toHaveCount(1)
        ->and($groups->first()['label'])->toBeNull()
        ->and($groups->first()['products']->first()->name)->toBe('Coca-Cola');
});

it('groups by shelf, each section titled, when ordering by category', function (): void {
    $groups = Livewire::actingAs($this->manager)
        ->test('pages::bar.products')
        ->viewData('groups');

    expect($groups->pluck('label')->all())->toBe(['Bières', 'Softs']);
});

it('loads the stock of every product in a bounded number of queries', function (): void {
    // Le prérequis de l'écran : getStockAttribute fait deux SUM par lecture, donc
    // ~80 requêtes pour 40 produits sans agrégation. Le test compare deux tailles
    // de catalogue plutôt qu'un seuil absolu : c'est la *croissance* qui tue.
    $count = fn (): int => (function (): int {
        $n = 0;
        DB::listen(function () use (&$n): void {
            $n++;
        });
        Livewire::actingAs($this->manager)->test('pages::bar.products')->viewData('groups');

        return $n;
    })();

    $withTwo = $count();

    for ($i = 0; $i < 18; $i++) {
        stockedProduct('Produit ' . $i, 150, $this->beers->id, 5, $this->manager->id);
    }

    $withTwenty = $count();

    expect($withTwenty)->toBeLessThanOrEqual($withTwo + 2,
        "Le stock n'est pas agrégé : {$withTwo} requêtes pour 2 produits, {$withTwenty} pour 20.");
});

it('keeps the price out of the grid, where a slip would reach every later sale', function (): void {
    // Le prix se lit dans le tableau et ne s'y modifie pas : l'auto-save n'a pas
    // d'annulation, et un prix mal frappé s'applique à toutes les ventes suivantes
    // sans que rien ne le dise. Il vit donc dans le tiroir, derrière Enregistrer.
    $html = Livewire::actingAs($this->manager)->test('pages::bar.products')->html();

    expect($html)->toContain('updateStock')
        ->and($html)->toContain('updateThreshold')
        ->and($html)->toContain('updateAvailability')
        ->and($html)->not->toContain('updatePrice');
});

it('drops the columns a thumb does not need, and keeps the ones it does', function (): void {
    // Le tableau reste un tableau sous `lg` — une carte par produit rendrait
    // exactement la densité que cet écran supprime. Ce sont donc les colonnes qui
    // se replient : prix et seuil partent, produit / stock / dispo restent.
    $headers = collect(Livewire::actingAs($this->manager)->test('pages::bar.products')->viewData('headers'))
        ->keyBy('key');

    expect($headers['price']['class'] ?? '')->toContain('hidden lg:table-cell')
        ->and($headers['threshold']['class'] ?? '')->toContain('hidden lg:table-cell')
        ->and($headers['stock']['class'] ?? '')->not->toContain('hidden')
        ->and($headers['available']['class'] ?? '')->not->toContain('hidden')
        ->and($headers['name']['class'] ?? '')->not->toContain('hidden');
});
