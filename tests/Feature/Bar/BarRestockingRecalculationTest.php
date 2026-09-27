<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarRestockingAdjustment;
use App\Domains\Bar\Services\BarRestockingSettings;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| Bar — le réassort automatique du vendredi
|--------------------------------------------------------------------------
|
| Décidé le 2026-09-27. Le vendredi à 6 h 05, la soirée du jeudi est close : les
| produits en automatique reprennent le min et le max que suggèrent leurs ventes.
| Seulement si ça bouge d'au moins 15 % — 72 ne devient pas 73 —, jamais un
| produit qui ne se vend plus (on le signale, on n'y touche pas), jamais un
| produit hors réassort. Chaque ajustement est gardé pour le digest du samedi.
|
*/

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-25 06:05'));
    $this->beers = BarCategory::create(['name' => 'Bières']);
});

function restockingRecalculationProduct(string $name, BarCategory $category, ?int $min, ?int $max, ?string $mode = null): BarProduct
{
    return BarProduct::create([
        'name' => $name, 'sale_price' => 200, 'is_available' => 1, 'category_id' => $category->id,
        'low_stock_threshold' => $min, 'max_stock' => $max, 'restocking_mode' => $mode,
    ]);
}

/** Une vente réglée le jeudi soir : 10 par semaine suggèrent 10 et 30. */
function restockingRecalculationSale(BarProduct $product, int $quantity): void
{
    $order = new BarOrder(['total_price' => 0, 'is_paid' => 1, 'payment_method' => 'cash']);
    $order->created_at = Carbon::parse('2026-09-24 21:00');
    $order->save();
    $order->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => 200, 'total_price' => 200 * $quantity]);
}

it('sets the products in automatic mode to what their sales suggest, signed by the system', function (): void {
    app(BarRestockingSettings::class)->setAutomatic(true);
    $jupiler = restockingRecalculationProduct('Jupiler', $this->beers, min: 6, max: 20);
    restockingRecalculationSale($jupiler, 10);

    $this->artisan('bar:restocking-recalculate')->assertSuccessful();

    expect($jupiler->fresh())
        ->low_stock_threshold->toBe(10)
        ->max_stock->toBe(30)
        ->restocking_adjusted_at->not->toBeNull();

    expect(BarRestockingAdjustment::query()->sole())
        ->product_id->toBe($jupiler->id)
        ->old_min->toBe(6)->new_min->toBe(10)
        ->old_max->toBe(20)->new_max->toBe(30);

    $activity = Activity::query()->where('subject_type', $jupiler->getMorphClass())->where('event', 'updated')->latest('id')->first();
    expect($activity->causer_id)->toBeNull();
});

it('follows each product rather than the bar when they differ', function (): void {
    // Le bar reste manuel, mais la Jupiler est toujours en automatique…
    $jupiler = restockingRecalculationProduct('Jupiler', $this->beers, min: 6, max: 20, mode: 'auto');
    // … et avec le bar en automatique, le Coca resterait manuel.
    $coca = restockingRecalculationProduct('Coca-Cola', $this->beers, min: 6, max: 20, mode: 'manual');
    // Sans avis propre, le Fanta suit le bar, qui est manuel.
    $fanta = restockingRecalculationProduct('Fanta', $this->beers, min: 6, max: 20);
    foreach ([$jupiler, $coca, $fanta] as $product) {
        restockingRecalculationSale($product, 10);
    }

    $this->artisan('bar:restocking-recalculate')->assertSuccessful();

    expect($jupiler->fresh()->max_stock)->toBe(30)
        ->and($coca->fresh()->max_stock)->toBe(20)
        ->and($fanta->fresh()->max_stock)->toBe(20);

    app(BarRestockingSettings::class)->setAutomatic(true);
    $this->artisan('bar:restocking-recalculate')->assertSuccessful();

    expect($coca->fresh()->max_stock)->toBe(20)
        ->and($fanta->fresh()->max_stock)->toBe(30);
});

it('leaves alone what moves less than 15 %, what no longer sells and what is out of restocking', function (): void {
    app(BarRestockingSettings::class)->setAutomatic(true);
    // 10 et 30 suggérés : 9 et 28 n'en sont qu'à 10 % et 7 %.
    $close = restockingRecalculationProduct('Jupiler', $this->beers, min: 9, max: 28);
    restockingRecalculationSale($close, 10);
    // Plus aucune vente : on signale, on ne touche pas.
    $asleep = restockingRecalculationProduct('Aquarius', $this->beers, min: 4, max: 12);
    // Sans max, hors réassort : l'automatique ne l'y fait pas entrer.
    $outside = restockingRecalculationProduct('Duvel', $this->beers, min: null, max: null);
    restockingRecalculationSale($outside, 10);

    $this->artisan('bar:restocking-recalculate')->assertSuccessful();

    expect($close->fresh())->low_stock_threshold->toBe(9)->max_stock->toBe(28)
        ->and($asleep->fresh())->low_stock_threshold->toBe(4)->max_stock->toBe(12)
        ->and($outside->fresh())->low_stock_threshold->toBeNull()->max_stock->toBeNull()
        ->and(BarRestockingAdjustment::query()->count())->toBe(0);
});

it('is scheduled on Friday at 6:05, once Thursday evening is closed', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'bar:restocking-recalculate'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('5 6 * * 5');
});

it('recalculates on demand from the restocking settings, as Friday would', function (): void {
    app(BarRestockingSettings::class)->setAutomatic(true);
    $jupiler = restockingRecalculationProduct('Jupiler', $this->beers, min: 6, max: 20);
    restockingRecalculationSale($jupiler, 10);

    Livewire::actingAs(User::factory()->withRole(Role::STORE_KEEPER)->create())
        ->test('pages::bar.products')
        ->call('recalculateRestocking')
        ->assertHasNoErrors();

    expect($jupiler->fresh())
        ->low_stock_threshold->toBe(10)
        ->max_stock->toBe(30)
        ->and(BarRestockingAdjustment::query()->count())->toBe(1);
});

it('leaves the on-demand recalculation to whoever manages the stock', function (): void {
    Livewire::actingAs(User::factory()->withRole(Role::BARMAN)->create())
        ->test('pages::bar.products')
        ->call('recalculateRestocking')
        ->assertForbidden();
});
