<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarInventory;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\Bar\Services\LegacyInventoryCorrections;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\BarInventoryCause;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Bar — les corrections faites avant l'inventaire
|--------------------------------------------------------------------------
|
| Avant l'inventaire, le stock se corrigeait d'un champ : un mouvement
| « Inventory count », un auteur, une heure, et rien sur ce qui s'était passé.
| Ces corrections deviennent des inventaires « Historique », un par auteur et
| par journée : on ne sait pas ce qui s'est passé, elles disent « Je ne sais
| pas », et leur valeur est estimée au prix d'aujourd'hui. Rien n'est envoyé, et
| le stock ne bouge pas.
|
*/

beforeEach(function (): void {
    $this->marc = User::factory()->withRole(Role::STORE_KEEPER)->create(['first_name' => 'Marc', 'last_name' => 'Dubois']);
    $category = BarCategory::create(['name' => 'Bières']);
    $this->jupiler = BarProduct::create(['name' => 'Jupiler', 'sale_price' => 150, 'is_available' => 1, 'category_id' => $category->id]);
    $this->leffe = BarProduct::create(['name' => 'Leffe', 'sale_price' => 250, 'is_available' => 1, 'category_id' => $category->id]);

    legacyMovement($this->jupiler, BarStockMovement::TYPE_IN, 20, 'Opening inventory', null, '2026-05-05 10:00');
    legacyMovement($this->leffe, BarStockMovement::TYPE_IN, 10, 'Opening inventory', null, '2026-05-05 10:00');
});

function legacyMovement(BarProduct $product, string $type, int $quantity, string $reason, ?User $by, string $at): void
{
    $movement = BarStockMovement::create([
        'product_id' => $product->id,
        'quantity' => $quantity,
        'remaining_quantity' => $type === BarStockMovement::TYPE_IN ? $quantity : 0,
        'movement_type' => $type,
        'reason' => $reason,
        'created_by' => $by?->id,
    ]);

    $movement->forceFill(['created_at' => Carbon::parse($at), 'updated_at' => Carbon::parse($at)])->saveQuietly();
}

it('turns the corrections of one person on one day into a single historical inventory', function (): void {
    legacyMovement($this->jupiler, BarStockMovement::TYPE_OUT, 6, 'Inventory count', $this->marc, '2026-09-30 20:10');
    legacyMovement($this->leffe, BarStockMovement::TYPE_IN, 2, 'Inventory count', $this->marc, '2026-09-30 20:12');

    expect(app(LegacyInventoryCorrections::class)->import())->toBe(1);

    $inventory = BarInventory::query()->sole();

    Livewire::actingAs($this->marc)
        ->test('pages::bar.inventories')
        ->assertSee(__('Historical'))
        ->assertSee('Marc Dubois')
        ->assertSee('−6')
        ->assertSee('+2');

    Livewire::actingAs($this->marc)
        ->test('pages::bar.inventory-show', ['inventory' => $inventory])
        ->assertSee(BarInventoryCause::Unknown->label())
        ->assertSee(__('Estimated at today\'s price'))
        ->assertSee(euros(-900 + 500));

    expect(BarProduct::query()->withStock()->find($this->jupiler->id)->stock)->toBe(14);
});

it('reads what was expected and counted from the stock just before the correction', function (): void {
    legacyMovement($this->jupiler, BarStockMovement::TYPE_OUT, 3, 'Sale', null, '2026-09-29 21:00');
    legacyMovement($this->jupiler, BarStockMovement::TYPE_OUT, 6, 'Inventory count', $this->marc, '2026-09-30 20:10');

    app(LegacyInventoryCorrections::class)->import();

    $line = BarInventory::query()->sole()->lines()->sole();

    expect($line->expected)->toBe(17)->and($line->counted)->toBe(11);
});

it('keeps two people, or two days, apart', function (): void {
    $sophie = User::factory()->withRole(Role::STORE_KEEPER)->create();
    legacyMovement($this->jupiler, BarStockMovement::TYPE_OUT, 1, 'Inventory count', $this->marc, '2026-09-19 10:00');
    legacyMovement($this->jupiler, BarStockMovement::TYPE_OUT, 1, 'Inventory count', $this->marc, '2026-09-30 10:00');
    legacyMovement($this->leffe, BarStockMovement::TYPE_OUT, 1, 'Inventory count', $sophie, '2026-09-30 10:05');

    expect(app(LegacyInventoryCorrections::class)->import())->toBe(3);
});

it('imports nothing twice', function (): void {
    legacyMovement($this->jupiler, BarStockMovement::TYPE_OUT, 6, 'Inventory count', $this->marc, '2026-09-30 20:10');

    app(LegacyInventoryCorrections::class)->import();

    expect(app(LegacyInventoryCorrections::class)->import())->toBe(0)
        ->and(BarInventory::query()->count())->toBe(1);
});
