<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Role;
use Carbon\Carbon;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Bar — le comptoir express
|--------------------------------------------------------------------------
|
| On sert d'abord, on décide ensuite : « Terminer » propose cash, QR, ardoise ou
| verre du club. Le cash et le verre du club n'ont plus de confirmation, ils
| s'annulent pendant dix secondes : la commande est enregistrée non payée, et le
| règlement ne part qu'au bout du délai. Ces tests tiennent ce délai — c'est lui
| qui garde la règle « on ne rouvre pas une commande payée ».
|
*/

/** Une équipe visiteuse attendue ce soir chez nous ; renvoie son nom complet. */
function counterVisitingTeam(string $letter, string $category = 'MEN', string $clubName = "Braine-L'Alleud"): string
{
    $season = Season::query()->where('is_active', true)->first() ?? Season::factory()->create(['is_active' => true]);
    $league = League::create([
        'division' => '1A',
        'level' => 'PROVINCIAL_BW',
        'category' => $category,
        'season_id' => $season->id,
    ]);
    $ownClub = Club::query()->where('is_own_club', true)->sole();
    $visitingClub = Club::query()->where('name', $clubName)->first() ?? Club::factory()->create(['name' => $clubName]);
    $ownTeam = Team::create([
        'name' => chr(65 + Team::query()->where('club_id', $ownClub->id)->count()),
        'season_id' => $season->id,
        'league_id' => $league->id,
        'club_id' => $ownClub->id,
    ]);
    $visitingTeam = Team::create([
        'name' => $letter,
        'season_id' => $season->id,
        'league_id' => $league->id,
        'club_id' => $visitingClub->id,
    ]);

    Interclub::create([
        'address' => 'Clubhouse',
        'start_date_time' => now()->copy()->setTime(20, 0),
        'total_players' => 4,
        'visited_team_id' => $ownTeam->id,
        'visiting_team_id' => $visitingTeam->id,
        'season_id' => $season->id,
        'league_id' => $league->id,
    ]);

    return $visitingTeam->fullName();
}

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-11-07 20:30:00'));

    Club::factory()->ownClub()->create();
    Club::forgetOwnClub();
    CashRegister::create(['name' => 'Caisse principale']);

    $this->barman = User::factory()->create();
    $this->barman->assignRole(Role::BARMAN->value);

    $category = BarCategory::create(['name' => 'Bières']);

    $this->beer = BarProduct::create([
        'name' => 'Jupiler',
        'sale_price' => 300,
        'is_available' => 1,
        'category_id' => $category->id,
    ]);

    BarStockMovement::create([
        'product_id' => $this->beer->id,
        'quantity' => 50,
        'remaining_quantity' => 50,
        'movement_type' => BarStockMovement::TYPE_IN,
        'created_by' => $this->barman->id,
    ]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('records a cash round at once, and settles it only when the undo delay is over', function (): void {
    $counter = Livewire::actingAs($this->barman)
        ->test('pages::bar.counter')
        ->call('add', $this->beer->id)
        ->call('add', $this->beer->id)
        ->call('payCash');

    $order = BarOrder::query()->sole();

    // Enregistrée, stock sorti, mais pas encore payée : c'est ce qui permet d'annuler.
    expect($order->is_paid)->toBe(0)
        ->and((int) $order->total_price)->toBe(600)
        ->and($this->beer->fresh()->stock)->toBe(48)
        ->and(session('bar_pending.order_id'))->toBe($order->id)
        ->and(CashRegisterEntry::query()->count())->toBe(0);

    $counter->call('confirmPending');

    expect($order->fresh()->is_paid)->toBe(1)
        ->and($order->fresh()->payment_method)->toBe('cash')
        ->and(CashRegisterEntry::query()->sole()->amount)->toBe(600)
        ->and(session('bar_pending'))->toBeNull();
});

it('gives the round back to the cart when the cash is cancelled, without paying anything', function (): void {
    $counter = Livewire::actingAs($this->barman)
        ->test('pages::bar.counter')
        ->call('add', $this->beer->id)
        ->call('add', $this->beer->id)
        ->call('payCash')
        ->call('cancelPending');

    $order = BarOrder::query()->sole();

    expect($order->is_paid)->toBe(0)
        ->and(session('cart'))->toBe([$this->beer->id => 2])
        ->and(session('bar_pending'))->toBeNull();

    // Régler de nouveau reprend la même commande : pas de doublon, pas de stock sorti deux fois.
    $counter->call('payCash')->call('confirmPending');

    expect(BarOrder::query()->count())->toBe(1)
        ->and($order->fresh()->is_paid)->toBe(1)
        ->and($this->beer->fresh()->stock)->toBe(48);
});

it('closes the undo delay as soon as the next round starts', function (): void {
    $counter = Livewire::actingAs($this->barman)
        ->test('pages::bar.counter')
        ->call('add', $this->beer->id)
        ->call('payCash');

    $first = BarOrder::query()->sole();

    $counter->call('add', $this->beer->id);

    expect($first->fresh()->is_paid)->toBe(1)
        ->and(session('cart'))->toBe([$this->beer->id => 1]);
});

it('settles a round left in its undo delay on the next visit to the counter', function (): void {
    // Un barman qui quitte l'écran pendant le décompte ne laisse rien en suspens.
    Livewire::actingAs($this->barman)
        ->test('pages::bar.counter')
        ->call('add', $this->beer->id)
        ->call('payCash');

    $this->travel(11)->seconds();

    Livewire::actingAs($this->barman)->test('pages::bar.counter');

    expect(BarOrder::query()->sole()->is_paid)->toBe(1);
});

it('sends a QR round to the payment screen with the code open', function (): void {
    Livewire::actingAs($this->barman)
        ->test('pages::bar.counter')
        ->call('add', $this->beer->id)
        ->call('payQr')
        ->assertRedirect(route('bar.payment.show', ['order' => BarOrder::query()->sole()->id, 'method' => 'qr']));
});

it('adds a round to an open tab without rewriting what it already holds', function (): void {
    Livewire::actingAs($this->barman)
        ->test('pages::bar.counter')
        ->call('add', $this->beer->id)
        ->call('add', $this->beer->id)
        ->call('putOnTab', 'Alpa A');

    Livewire::actingAs($this->barman)
        ->test('pages::bar.counter')
        ->call('add', $this->beer->id)
        // Tapé autrement : la comparaison ignore la casse et les accents.
        ->call('putOnTab', 'alpa a');

    $tab = BarOrder::query()->sole();

    expect($tab->name)->toBe('Alpa A')
        ->and((int) $tab->items()->sum('quantity'))->toBe(3)
        // Une ligne par produit : la modification d'une ardoise relit les quantités par produit.
        ->and($tab->items()->count())->toBe(1)
        ->and((int) $tab->total_price)->toBe(900)
        ->and($this->beer->fresh()->stock)->toBe(47);
});

it('gives a club round to each visiting team of the night', function (): void {
    $teamB = counterVisitingTeam('B');
    $teamF = counterVisitingTeam('F', 'VETERANS');

    foreach ([$teamB, $teamF] as $team) {
        Livewire::actingAs($this->barman)
            ->test('pages::bar.counter')
            ->call('add', $this->beer->id)
            ->call('giveClubRound', $team, false)
            ->call('confirmPending');
    }

    expect(BarOrder::query()->where('payment_method', 'offered')->pluck('reason')->sort()->values()->all())
        ->toBe(["Braine-L'Alleud B", "Braine-L'Alleud F"]);
});

it('refuses a club round above the cap of the team, before recording anything', function (): void {
    $veterans = counterVisitingTeam('F', 'VETERANS');

    $counter = Livewire::actingAs($this->barman)->test('pages::bar.counter');

    foreach (range(1, 4) as $ignored) {
        $counter->call('add', $this->beer->id);
    }

    $counter->call('giveClubRound', $veterans, false);

    expect(BarOrder::query()->count())->toBe(0)
        ->and(session('cart'))->toBe([$this->beer->id => 4]);
});

it('settles an open tab from its card, and cancelling leaves the tab open', function (): void {
    Livewire::actingAs($this->barman)
        ->test('pages::bar.counter')
        ->call('add', $this->beer->id)
        ->call('putOnTab', 'Gilles');

    $tab = BarOrder::query()->sole();

    Livewire::actingAs($this->barman)
        ->test('pages::bar.counter')
        ->call('settleTabCash', $tab->id)
        ->call('cancelPending');

    expect($tab->fresh()->is_paid)->toBe(0)
        // Rien à recharger : l'ardoise n'a pas quitté la file.
        ->and(session('cart'))->toBeNull();

    Livewire::actingAs($this->barman)
        ->test('pages::bar.counter')
        ->call('settleTabCash', $tab->id)
        ->call('confirmPending');

    expect($tab->fresh()->is_paid)->toBe(1)
        ->and($tab->fresh()->open_name_key)->toBeNull();
});

it('suggests the teams playing tonight as tab names', function (): void {
    counterVisitingTeam('B');

    Livewire::actingAs($this->barman)
        ->test('pages::bar.counter')
        ->call('add', $this->beer->id)
        ->call('openTabSheet')
        ->set('tabNameInput', 'brai')
        ->assertSee("Braine-L'Alleud B");
});
