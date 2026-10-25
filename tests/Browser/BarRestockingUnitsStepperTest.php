<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarRestocking;
use App\Domains\Bar\Services\RestockingTrips;
use App\Domains\ClubAdmin\Users\Models\User;

/**
 * Au retour du magasin, ce qui a été acheté se compte en unités : le rayon
 * n'avait pas forcément le conditionnement prévu. Les boutons − / + restent le
 * raccourci du cas courant — toujours un conditionnement entier, quelle que soit
 * la valeur tapée — et le compteur ne descend jamais sous zéro.
 *
 * Les boutons agissent dans le navigateur, sans aller-retour au serveur : aucun
 * test Livewire ne les voit.
 */
it('counts what was bought in units, a whole pack per tap', function (): void {
    $shopper = User::factory()->isAdmin()->create();
    makeActiveSeason();
    $this->actingAs($shopper);

    BarProduct::create([
        'name' => 'Jupiler', 'sale_price' => 200, 'is_available' => 1,
        'category_id' => BarCategory::create(['name' => 'Bières'])->id,
        'low_stock_threshold' => 4, 'max_stock' => 8, 'pack_size' => 8, 'pack_label' => 'casier',
    ]);
    app(RestockingTrips::class)->start($shopper);
    BarRestocking::inProgress()->lines()->update(['in_cart' => true]);

    $field = '[aria-label="' . __('Units bought: :product', ['product' => 'Jupiler']) . '"]';
    $more = '[aria-label="' . __('One pack more: :product', ['product' => 'Jupiler']) . '"]';
    $less = '[aria-label="' . __('One pack less: :product', ['product' => 'Jupiler']) . '"]';
    $value = fn ($page): string => (string) $page->script("document.querySelector('{$field}').value");

    $page = visit(route('bar.restocking.index'))->resize(390, 844);
    $page->assertNoJavaScriptErrors()
        ->click('button[wire\\:click="openClosing"]')
        ->wait(1);

    expect($value($page))->toBe('8', 'coché = les unités proposées');
    $page->assertSee('1 × casier');

    // Le magasin n'avait qu'un pack de 6.
    $page->fill($field, '6')->wait(0.5);
    $page->assertSee(__(':units units', ['units' => 6]));

    $page->click($more);
    expect($value($page))->toBe('14', '+ ajoute un conditionnement entier, sans s\'aligner sur 16');

    $page->click($less)->click($less);
    expect($value($page))->toBe('0', '− ne descend jamais sous zéro');
})->group('bar');
