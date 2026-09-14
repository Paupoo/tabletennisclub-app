<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| La carte du bar, côté public
|--------------------------------------------------------------------------
|
| Trois surfaces, une seule source : l'écran casté derrière le bar, la page
| qu'on ouvre en scannant le QR posé sur les tables, et la feuille qui porte
| ce QR. Aucune n'écrit : elles lisent le catalogue du bar tel que la caisse
| le tient.
|
| Ce qui est verrouillé ici :
|
| - un produit retiré (`is_available = 0`) n'apparaît nulle part : c'est une
|   décision du comptoir, la vitrine la respecte ;
| - un produit à zéro reste affiché, marqué épuisé — le client voit qu'il
|   existe d'habitude, et personne ne le demande ;
| - la « Bière du mois » quitte la liste des bières pour son encart, et
|   l'encart disparaît quand elle est épuisée ;
| - la page ne coûte pas une requête par produit : le stock est agrégé.
|
*/

/** Crée un produit du bar avec le stock voulu, sans passer par la caisse. */
function barMenuProduct(string $name, int $cents, BarCategory $category, int $stock = 10, bool $available = true): BarProduct
{
    $product = BarProduct::create([
        'name' => $name,
        'sale_price' => $cents,
        'is_available' => $available,
        'category_id' => $category->id,
    ]);

    if ($stock > 0) {
        BarStockMovement::create([
            'product_id' => $product->id,
            'movement_type' => BarStockMovement::TYPE_IN,
            'quantity' => $stock,
            'remaining_quantity' => $stock,
            'reason' => 'Inventaire de test',
        ]);
    }

    return $product;
}

/** Compte les requêtes que coûte un affichage de la page. */
function countQueriesOn(string $url): int
{
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    test()->get($url)->assertOk();

    return $queries;
}

beforeEach(function (): void {
    $this->beers = BarCategory::create(['name' => 'Bières']);
    $this->softs = BarCategory::create(['name' => 'Softs']);
});

it('shows what the bar serves, with its price', function (): void {
    barMenuProduct('Jupiler', 200, $this->beers);
    barMenuProduct('Coca Zéro', 250, $this->softs);

    $response = $this->get(route('public.bar.menu'));

    $response->assertOk();
    $response->assertSee('Jupiler');
    $response->assertSee('Bières');
    // Le format de la carte : pas de « ,00 » inutile, et jamais de coupure
    // avant le symbole — une ligne cassée coûte deux crans de typo à l'écran.
    $response->assertSee("2\u{A0}€", false);
    $response->assertSee("2,50\u{A0}€", false);
});

it('keeps an empty product on the menu, marked as sold out', function (): void {
    barMenuProduct('Coca Cola', 200, $this->softs, stock: 0);

    $response = $this->get(route('public.bar.menu'));

    $response->assertOk();
    $response->assertSee('Coca Cola');
    $response->assertSee('Épuisé', false);
});

it('lifts the beer of the month out of the list and into its own spot', function (): void {
    $featured = BarCategory::create(['name' => 'Bière du mois']);
    barMenuProduct('Tripel Karmeliet', 300, $featured);
    barMenuProduct('Jupiler', 200, $this->beers);

    $response = $this->get(route('public.bar.menu'));

    $response->assertOk();
    $response->assertSee('Tripel Karmeliet');
    $response->assertSeeInOrder(['Bière du mois', 'Tripel Karmeliet', 'Bières', 'Jupiler'], false);
    // Une seule fois : la bière élue quitte la liste des bières.
    expect(substr_count($response->getContent(), 'Tripel Karmeliet'))->toBe(1);
});

it('drops the beer of the month when it runs out, rather than greying it', function (): void {
    $featured = BarCategory::create(['name' => 'Bière du mois']);
    barMenuProduct('Tripel Karmeliet', 300, $featured, stock: 0);
    barMenuProduct('Jupiler', 200, $this->beers);

    $response = $this->get(route('public.bar.menu'));

    $response->assertOk();
    $response->assertDontSee('Tripel Karmeliet');
    $response->assertDontSee('Bière du mois', false);
    $response->assertSee('Jupiler');
});

it('leaves a withdrawn product off the menu entirely', function (): void {
    barMenuProduct('Jupiler', 200, $this->beers);
    barMenuProduct('Cuvée des Trolls', 300, $this->beers, stock: 12, available: false);

    $response = $this->get(route('public.bar.menu'));

    $response->assertOk();
    $response->assertSee('Jupiler');
    $response->assertDontSee('Cuvée des Trolls', false);
});

/*
 * Le stock est calculé, pas stocké : lu produit par produit, il coûte deux
 * requêtes par ligne — une centaine pour un vrai catalogue, à chaque affichage,
 * sur une page que le téléviseur recharge toutes les minutes.
 */
it('reads the whole catalogue in a fixed number of queries', function (): void {
    barMenuProduct('Jupiler', 200, $this->beers);
    barMenuProduct('Coca Zéro', 250, $this->softs);

    $small = countQueriesOn(route('public.bar.menu'));

    foreach (range(1, 18) as $i) {
        barMenuProduct('Bière ' . $i, 250, $this->beers);
    }

    expect(countQueriesOn(route('public.bar.menu')))->toBe($small);
});

it('serves the cast screen, which reloads itself', function (): void {
    barMenuProduct('Jupiler', 200, $this->beers);

    $response = $this->get(route('public.bar.screen'));

    $response->assertOk();
    $response->assertSee('Jupiler');
    $response->assertSee("2\u{A0}€", false);
    // Un écran casté n'a pas de clavier pour faire F5.
    $response->assertSee('data-refresh="60"', false);
});

/*
 * La colonne publicitaire ne se saisit pas deux fois : le stage publié une
 * fois sur le site s'affiche au bar, et s'en retire tout seul le lendemain de
 * sa date.
 */
it('carries the club announcements onto both surfaces', function (): void {
    barMenuProduct('Jupiler', 200, $this->beers);

    EventPost::factory()->create([
        'title' => 'Stage de la Toussaint',
        'status' => EventPostStatusEnum::PUBLISHED,
        'event_date' => now()->addMonth(),
    ]);
    NewsPost::factory()->create([
        'title' => 'Montée en provinciale 2',
        'status' => NewsPostStatusEnum::PUBLISHED,
    ]);

    $this->get(route('public.bar.menu'))
        ->assertOk()
        ->assertSee('Stage de la Toussaint')
        ->assertSee('Montée en provinciale 2');

    $this->get(route('public.bar.screen'))
        ->assertOk()
        ->assertSee('Stage de la Toussaint');
});

it('drops an announcement the day its date is past', function (): void {
    barMenuProduct('Jupiler', 200, $this->beers);

    EventPost::factory()->create([
        'title' => 'Tournoi du club',
        'status' => EventPostStatusEnum::PUBLISHED,
        'event_date' => now()->subDay(),
    ]);

    $this->get(route('public.bar.screen'))
        ->assertOk()
        ->assertDontSee('Tournoi du club');
});

it('shows the sponsors the home page shows, from one source', function (): void {
    barMenuProduct('Jupiler', 200, $this->beers);

    $sponsor = config('club.sponsors.0.name');

    expect($sponsor)->not->toBeEmpty();

    $this->get(route('public.bar.screen'))->assertOk()->assertSee($sponsor, false);
    $this->get(route('home'))->assertOk()->assertSee($sponsor, false);
});

it('prints a sheet carrying the QR and the address in plain sight', function (): void {
    $response = $this->get(route('public.bar.flyer'));

    $response->assertOk();
    $response->assertSee('data:image/png;base64,', false);
    $response->assertSee(route('public.bar.menu'), false);
});

/*
 * Les trois surfaces ne servent à rien si le barman ne sait pas les ouvrir. Le
 * back-office du bar porte donc les liens : l'écran à caster, la page que les
 * clients scannent, et la feuille à imprimer.
 */
it('hands the barman the links to set the room up', function (): void {
    $barman = User::factory()->withRole(Role::BARMAN)->create();

    test()->actingAs($barman);
    $html = (string) test()->blade('<x-admin.navigation :user="$user" />', ['user' => $barman]);

    expect($html)
        ->toContain(route('public.bar.screen'))
        ->toContain(route('public.bar.menu'))
        ->toContain(route('public.bar.flyer'));

    // Dans un nouvel onglet : caster le back-office à la place de la carte
    // laisserait le barman sans caisse, devant une salle qui attend.
    expect($html)->toMatch('/href="' . preg_quote(route('public.bar.screen'), '/') . '"[^>]*target="_blank"/');
});
