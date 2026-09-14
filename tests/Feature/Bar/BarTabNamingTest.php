<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Bar — les ardoises nommées (issue #125)
|--------------------------------------------------------------------------
|
| La file d'encaissement n'affichait qu'un numéro. Au bar on retient « la tournée
| d'Alpa A », pas « la commande #47 » : une heure plus tard, et surtout quand ce
| n'est plus le même barman, plus personne ne sait quelle ligne encaisser.
|
| Le nom identifie une ARDOISE, pas une commande. Trois conséquences que ces
| tests tiennent :
|
| - saisir un nom déjà ouvert le REJOINT au lieu d'être refusé. L'unicité devient
|   structurelle — deux ardoises homonymes sont impossibles par construction, et
|   non parce qu'une validation les rejette ;
| - la comparaison se fait sur une clé normalisée, en PHP. MySQL est insensible à
|   la casse et aux accents, SQLite non : déléguer à la collation donnerait une
|   règle verte en test et fausse en production ;
| - la clé est libérée au paiement, si bien qu'« Alpa A » resserve le même soir
|   sans que l'historique perde son nom.
|
| Le client de passage, lui, ne nomme rien : il paie sur-le-champ, il n'entre
| jamais dans la file, il n'y a rien à retrouver.
|
*/

beforeEach(function (): void {
    $this->barman = User::factory()->create();
    $this->barman->assignRole(Role::BARMAN->value);

    $this->colleague = User::factory()->create();
    $this->colleague->assignRole(Role::BARMAN->value);

    $category = BarCategory::create(['name' => 'Bières']);

    $this->product = BarProduct::create([
        'name' => 'Jupiler 25 cl',
        'sale_price' => 180,
        'is_available' => 1,
        'category_id' => $category->id,
    ]);

    BarStockMovement::create([
        'product_id' => $this->product->id,
        'quantity' => 50,
        'remaining_quantity' => 50,
        'movement_type' => BarStockMovement::TYPE_IN,
        'created_by' => $this->barman->id,
    ]);
});

/**
 * Ouvre une ardoise et y sert `$qty` bières, tap par tap, comme le ferait le barman.
 *
 * Les « + » passent par le composant et non par une session forcée : rejoindre une
 * ardoise précharge le panier de ce qu'elle porte déjà, et écraser ce panier ferait
 * dire au test ce qu'il veut entendre au lieu de ce que l'écran fait.
 */
function serveOnTab(User $user, string $name, int $productId, int $qty): void
{
    $counter = Livewire::actingAs($user)
        ->test('pages::bar.counter')
        ->set('tabNameInput', $name)
        ->call('openTab');

    for ($i = 0; $i < $qty; $i++) {
        $counter->call('add', $productId);
    }

    Livewire::actingAs($user)->test('pages::bar.cart')->call('validateOrder', 'validate');
}

it('asks who the round is for before showing the catalogue', function (): void {
    $this->actingAs($this->barman)
        ->get(route('bar.index'))
        ->assertOk()
        ->assertSee(__('Who is this round for?'));
});

it('names the tab it opens', function (): void {
    serveOnTab($this->barman, 'Alpa A', $this->product->id, 2);

    $order = BarOrder::query()->sole();

    expect($order->name)->toBe('Alpa A')
        ->and($order->open_name_key)->toBe('alpa a')
        ->and((int) $order->total_price)->toBe(360);
});

it('refuses to leave a tab open without a name', function (): void {
    // Une ardoise anonyme est exactement le problème qu'on répare : elle reste dans
    // la file, et personne ne sait à qui la présenter.
    session()->put('cart', [$this->product->id => 1]);

    Livewire::actingAs($this->barman)
        ->test('pages::bar.cart')
        ->call('validateOrder', 'validate')
        ->assertNoRedirect();

    expect(BarOrder::query()->count())->toBe(0);
});

it('joins the open tab instead of opening a second one of the same name', function (): void {
    serveOnTab($this->barman, 'Alpa A', $this->product->id, 2);
    serveOnTab($this->colleague, 'Alpa A', $this->product->id, 5);

    // Une seule ardoise, et elle porte les deux tournées : le second barman a rejoint
    // la première au lieu d'en ouvrir une jumelle, et son panier est arrivé préchargé
    // des 2 bières déjà servies. 2 + 5 = 7 × 1,80 €.
    $order = BarOrder::query()->sole();

    expect($order->name)->toBe('Alpa A')
        ->and((int) $order->total_price)->toBe(1260)
        ->and((int) $order->items()->sum('quantity'))->toBe(7);
});

it('recognises a tab through case, accents and stray spaces', function (string $typed): void {
    // Deux barmen ne tapent jamais pareil. Une comparaison stricte laisserait passer
    // précisément les doublons qu'on cherche à empêcher.
    serveOnTab($this->barman, 'Équipe A', $this->product->id, 1);
    serveOnTab($this->colleague, $typed, $this->product->id, 1);

    expect(BarOrder::query()->count())->toBe(1)
        // Le nom affiché reste la première saisie, accents compris.
        ->and(BarOrder::query()->sole()->name)->toBe('Équipe A');
})->with([
    'minuscules' => 'équipe a',
    'sans accent' => 'Equipe A',
    'capitales' => 'EQUIPE A',
    'espaces surnuméraires' => '  Équipe   A  ',
]);

it('keeps a dash as a distinguishing mark', function (): void {
    serveOnTab($this->barman, 'Alpa A', $this->product->id, 1);
    serveOnTab($this->colleague, 'Alpa-A', $this->product->id, 1);

    expect(BarOrder::query()->count())->toBe(2);
});

it('frees the name once the tab is settled, keeping it on the record', function (): void {
    serveOnTab($this->barman, 'Alpa A', $this->product->id, 2);
    $first = BarOrder::query()->sole();

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $first), ['method' => 'cash'])
        ->assertSessionHas('success');

    $first->refresh();

    expect($first->open_name_key)->toBeNull()
        ->and($first->name)->toBe('Alpa A');

    // Le même nom resserre une nouvelle ardoise, le même soir.
    serveOnTab($this->barman, 'Alpa A', $this->product->id, 1);

    expect(BarOrder::query()->count())->toBe(2)
        ->and(BarOrder::query()->whereNotNull('open_name_key')->count())->toBe(1);
});

it('lets a walk-in pay without naming anything', function (): void {
    Livewire::actingAs($this->barman)
        ->test('pages::bar.counter')
        ->call('openWalkIn')
        ->call('add', $this->product->id);

    Livewire::actingAs($this->barman)->test('pages::bar.cart')->call('validateOrder', 'pay_now');

    $order = BarOrder::query()->sole();

    expect($order->name)->toBeNull()
        ->and($order->open_name_key)->toBeNull();
});

it('lists open tabs alphabetically, not by number', function (): void {
    // « Vétérans » doit se classer avant « Zoé » : un tri octet par octet range tous
    // les accents après Z.
    serveOnTab($this->barman, 'Zoé', $this->product->id, 1);
    serveOnTab($this->barman, 'Alpa A', $this->product->id, 1);
    serveOnTab($this->barman, 'Vétérans C', $this->product->id, 1);

    $names = $this->actingAs($this->barman)
        ->get(route('bar.orders.index'))
        ->assertOk()
        ->viewData('orders')
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Alpa A', 'Vétérans C', 'Zoé']);
});

it('lets a colleague cash in and add to a tab they did not open', function (): void {
    // Un bar tourne en équipe : celui qui encaisse n'est presque jamais celui qui a
    // servi. `bar.orders.takeover` existait et n'était vérifiée nulle part — le
    // collègue tombait sur un 403 nu.
    serveOnTab($this->barman, 'Alpa A', $this->product->id, 2);
    $order = BarOrder::query()->sole();

    $this->actingAs($this->colleague)
        ->get(route('bar.payment.show', $order))
        ->assertOk();

    $this->actingAs($this->colleague)
        ->get(route('bar.orders.modify', $order))
        ->assertRedirect(route('bar.index'))
        ->assertSessionHas('success');
});

it('keeps deletion with the person who opened the tab', function (): void {
    // La reprise sert à encaisser et à compléter, pas à effacer : la suppression
    // restitue le stock et détruit les lignes, elle est irréversible.
    serveOnTab($this->barman, 'Alpa A', $this->product->id, 2);
    $order = BarOrder::query()->sole();

    $this->actingAs($this->colleague)
        ->delete(route('bar.orders.destroy', $order))
        ->assertSessionHas('error');

    expect(BarOrder::query()->whereKey($order->id)->exists())->toBeTrue();
});

it('renames a tab, and refuses a rename onto a name already open', function (): void {
    serveOnTab($this->barman, 'table du fond', $this->product->id, 1);
    serveOnTab($this->barman, 'Gilles', $this->product->id, 1);

    $vague = BarOrder::query()->where('name', 'table du fond')->sole();

    $this->actingAs($this->barman)
        ->post(route('bar.orders.rename', $vague), ['name' => 'Gilles'])
        ->assertSessionHas('error');

    expect($vague->fresh()->name)->toBe('table du fond');

    $this->actingAs($this->barman)
        ->post(route('bar.orders.rename', $vague), ['name' => 'Alpa A'])
        ->assertSessionHas('success');

    expect($vague->fresh()->name)->toBe('Alpa A')
        ->and($vague->fresh()->open_name_key)->toBe('alpa a');
});

it('sends the barman back to the counter after saving a tab', function (): void {
    // La boucle du bar est « servir → encaisser → servir le suivant ». Atterrir sur
    // la liste de ce qu'on vient de faire sort du flux au moment où le suivant attend.
    Livewire::actingAs($this->barman)
        ->test('pages::bar.counter')
        ->set('tabNameInput', 'Alpa A')
        ->call('openTab')
        ->call('add', $this->product->id);

    Livewire::actingAs($this->barman)
        ->test('pages::bar.cart')
        ->call('validateOrder', 'validate')
        ->assertRedirect(route('bar.index'));

    expect(session('success'))->toContain('Alpa A');
});

it('shows the tab it is serving, so the counter never lies about its state', function (): void {
    // Le défaut le plus sournois de l'ancien écran : en mode modification, cinq
    // éléments affirmaient « nouvelle commande » alors qu'on réécrivait la commande
    // d'un client, et le seul indice était un bouton dans la pilule flottante.
    $this->actingAs($this->barman)
        ->withSession(['bar_tab_name' => 'Alpa A'])
        ->get(route('bar.index'))
        ->assertOk()
        ->assertSee('Alpa A')
        ->assertDontSee(__('Who is this round for?'));
});
