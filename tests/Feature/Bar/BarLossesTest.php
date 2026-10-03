<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarInventory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\Bar\Services\BarInventories;
use App\Domains\Bar\Services\BarSalesReport;
use App\Domains\Bar\Services\RestockingSuggestions;
use App\Domains\Bar\Services\StockService;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\BarInventoryCause;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Bar — les pertes dans les chiffres
|--------------------------------------------------------------------------
|
| Ce qui a été bu sans passer en caisse, ou dont on ne sait pas ce qu'il est
| devenu, est parti chez quelqu'un : il faudra le racheter, la perte compte dans
| les moyennes des courses. La casse et le périmé ne comptent pas — on ne rachète
| pas plus de ce qui périme. Une perte constatée à l'inventaire s'est produite
| depuis le comptage précédent : elle est étalée sur les semaines avec ventes de
| cette période, pour ne pas faire bondir le max juste après chaque inventaire.
|
*/

beforeEach(function (): void {
    Notification::fake();
    $this->travelTo(Carbon::parse('2026-08-30 10:00'));

    $this->storeKeeper = User::factory()->withRole(Role::STORE_KEEPER)->create();
    $this->jupiler = BarProduct::create(['name' => 'Jupiler', 'sale_price' => 150, 'is_available' => 1, 'category_id' => BarCategory::create(['name' => 'Bières'])->id]);

    // Le stock d'ouverture, puis quatre vendredis à dix Jupiler.
    BarStockMovement::create(['product_id' => $this->jupiler->id, 'quantity' => 100, 'remaining_quantity' => 100, 'movement_type' => BarStockMovement::TYPE_IN]);

    foreach (['2026-09-04', '2026-09-11', '2026-09-18', '2026-09-25'] as $friday) {
        lossesSale($this->jupiler, 10, "{$friday} 21:00");
    }

    $this->travelTo(Carbon::parse('2026-09-26 10:00'));
});

function lossesSale(BarProduct $product, int $quantity, string $at): void
{
    $order = new BarOrder(['total_price' => 150 * $quantity, 'is_paid' => 1, 'payment_method' => 'Cash']);
    $order->created_at = Carbon::parse($at);
    $order->save();
    $order->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => 150, 'total_price' => 150 * $quantity]);
    app(StockService::class)->consumeFIFO($product->id, $quantity, 'Sale', orderId: $order->id);
}

function lossesInventory(User $by, BarProduct $product, int $missing, BarInventoryCause $cause): BarInventory
{
    $inventories = app(BarInventories::class);
    $inventory = $inventories->open($by);
    $stock = BarProduct::query()->withStock()->findOrFail($product->id)->stock;
    $inventories->count($inventory, $product->id, $stock - $missing, $by);
    if ($missing > 0) {
        $inventories->explain($inventory, $product->id, $cause);
    }
    $inventories->validate($inventory, $by);

    return $inventory;
}

it('counts a loss gone to someone in the shopping averages, spread over the weeks since the last count', function (BarInventoryCause $cause): void {
    expect(app(RestockingSuggestions::class)->all()[$this->jupiler->id])->toBe(['min' => 10, 'max' => 30]);

    lossesInventory($this->storeKeeper, $this->jupiler, 8, $cause);

    // Huit unités sur les quatre semaines avec ventes depuis l'ouverture : deux de
    // plus par semaine, 12 au lieu de 10.
    expect(app(RestockingSuggestions::class)->all()[$this->jupiler->id])->toBe(['min' => 12, 'max' => 36]);
})->with([BarInventoryCause::UnrecordedSale, BarInventoryCause::Unknown]);

it('leaves what broke or expired out of the shopping averages', function (BarInventoryCause $cause): void {
    lossesInventory($this->storeKeeper, $this->jupiler, 8, $cause);

    expect(app(RestockingSuggestions::class)->all()[$this->jupiler->id])->toBe(['min' => 10, 'max' => 30]);
})->with([BarInventoryCause::Broken, BarInventoryCause::Expired]);

it('spreads a loss only since the previous count of the product', function (): void {
    lossesInventory($this->storeKeeper, $this->jupiler, 0, BarInventoryCause::Unknown);
    $this->travelTo(Carbon::parse('2026-10-02 10:00'));
    lossesSale($this->jupiler, 10, '2026-10-02 21:00');
    $this->travelTo(Carbon::parse('2026-10-03 10:00'));

    // Comptée juste le 26 septembre, puis il en manque cinq le 3 octobre : tout
    // tombe dans la seule semaine avec ventes entre les deux.
    lossesInventory($this->storeKeeper, $this->jupiler, 5, BarInventoryCause::UnrecordedSale);

    // Cinq semaines actives (poids 1 ; 0,841 ; 0,707 ; 0,595 ; 0,5 — Σ 3,643) :
    // (15 + 10 × 2,643) / 3,643 = 11,37 par semaine.
    expect(app(RestockingSuggestions::class)->all()[$this->jupiler->id])->toBe(['min' => 12, 'max' => 35]);
});

it('shows the losses found in the period on the sales screen, with what happened', function (): void {
    lossesInventory($this->storeKeeper, $this->jupiler, 6, BarInventoryCause::Broken);
    $committee = User::factory()->isCommitteeMember()->create();

    Livewire::actingAs($committee)
        ->test('pages::bar.stats')
        ->set('firstDay', '2026-09-01')
        ->set('lastDay', '2026-09-30')
        ->assertSee(__('Inventory losses'))
        ->assertSeeHtml('data-losses="6"')
        ->assertSee(__(':count broken', ['count' => 6]));
});

it('adds the losses gone to someone to the weekly sales, like the shopping does', function (): void {
    lossesInventory($this->storeKeeper, $this->jupiler, 8, BarInventoryCause::UnrecordedSale);

    $report = app(BarSalesReport::class)->between(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));
    $jupiler = collect($report)->flatMap(fn (array $group): array => $group['products'])->firstWhere('id', $this->jupiler->id);

    // 40 vendues et 8 bues sans passer en caisse, sur 30 jours : 11,2 par semaine.
    expect($jupiler['units'])->toBe(40)
        ->and($jupiler['losses'])->toBe(8)
        ->and($jupiler['weekly'])->toBe(11.2);
});
