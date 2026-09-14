<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Users\Models\User;

/*
|--------------------------------------------------------------------------
| Bar — savoir dans quel écran on est
|--------------------------------------------------------------------------
|
| maryUI allume une entrée dès que l'URL courante *commence par* son lien. Les
| chemins du bar s'emboîtent — `/bar`, `/bar/orders`, `/bar/orders/history` —
| et trois entrées s'allumaient donc ensemble sur l'historique, « Nouvelle
| commande » restant allumée sur toutes les pages du bar.
|
| Un menu qui désigne trois écrans à la fois n'en désigne aucun.
|
| Ce qui est verrouillé ici : une seule entrée allumée par écran, et celle qui
| correspond. Les sous-écrans (encaisser une commande, la modifier) gardent
| allumée la liste d'où l'on vient, parce que c'est là qu'on est.
|
*/

function activeMenuPaths(User $user, string $url): array
{
    $html = (string) test()->actingAs($user)->get($url)->assertOk()->getContent();

    preg_match_all('/<a[^>]*mary-active-menu[^>]*href="([^"]+)"/', $html, $matches);

    return array_values(array_map(
        fn (string $href): string => (string) parse_url($href, PHP_URL_PATH),
        $matches[1],
    ));
}

beforeEach(function (): void {
    $this->barman = User::factory()->isAdmin()->create();
});

it('lights the entry of the screen you are on, and only that one', function (string $routeName, string $expected): void {
    expect(activeMenuPaths($this->barman, route($routeName)))->toBe([$expected]);
})->with([
    'le comptoir' => ['bar.index', '/bar'],
    'la file d\'encaissement' => ['bar.orders.index', '/bar/orders'],
    'l\'historique' => ['bar.orders.history', '/bar/orders/history'],
    'les produits' => ['bar.products.index', '/bar/products'],
    'les catégories' => ['bar.categories.index', '/bar/categories'],
]);

it('keeps the cashing-in list lit while an order is being settled', function (): void {
    $order = BarOrder::create([
        'created_by' => $this->barman->id,
        'total_price' => 250,
        'is_paid' => 0,
    ]);

    expect(activeMenuPaths($this->barman, route('bar.payment.show', $order)))->toBe(['/bar/orders']);
});
