<?php

declare(strict_types=1);

use App\Actions\Bar\RecordBarOrderPayment;
use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarOrderItem;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Carbon\Carbon;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Bar — l'argent du bar rejoint celui du club (issue #126)
|--------------------------------------------------------------------------
|
| L'argent du bar et l'argent du club vivaient dans deux mondes séparés : une
| commande réglée par QR n'était jamais rapprochée de la transaction bancaire
| correspondante, et le total du bar ne se recoupait avec les comptes qu'à la
| main, par la feuille de caisse.
|
| Ce qui est verrouillé ici :
|
| - seul le QR crée un `Payment` : le cash et l'offert relèvent du tiroir-caisse,
|   pas de la liste bancaire, et les y verser noierait l'écran du trésorier ;
| - le `Payment` naît **en attente**, jamais payé : le barman a encaissé, mais
|   l'argent n'est pas encore sur le compte du club. C'est précisément ce que le
|   trésorier doit rapprocher — et ce qui rend visible un QR jamais arrivé ;
| - la communication est celle que le QR montre déjà au client, pour que la
|   ligne du relevé bancaire et celle du trésorier portent le même texte ;
| - un rapprochement ne réécrit jamais rien sur la commande : `is_paid` dit
|   « quitte avec le barman », le `Payment` dit « arrivé sur le compte ». Le
|   bug de #122 ne peut donc pas se produire ici.
|
*/

/** Une commande servie et prête à être encaissée. */
function barOrderToSettle(User $barman, int $cents = 250): BarOrder
{
    return BarOrder::create([
        'created_by' => $barman->id,
        'total_price' => $cents,
        'is_paid' => 0,
    ]);
}

beforeEach(function (): void {
    // La journée de caisse commence à 6 h (CashSheetService) : entre minuit et
    // 6 h, une commande créée « maintenant » compte pour la veille et les tests
    // qui lisent la journée du jour échouaient. On se place en pleine journée.
    $this->travelTo(now()->setTime(14, 0));

    Club::factory()->ownClub()->create();
    $this->barman = User::factory()->isAdmin()->create();
    $this->treasurer = User::factory()->isAdmin()->create();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * Une équipe visiteuse attendue ce jour-là chez nous, et son nom complet
 * (« Visiting Club B ») : c'est l'équipe, et non le club, qui reçoit le verre.
 */
function barVisitingTeamForToday(?Carbon $fixtureDate = null, string $letter = 'B', string $category = 'MEN', string $clubName = 'Visiting Club'): string
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
    // Une équipe à nous par match : deux « A » dans la même catégorie seraient refusées.
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
        'start_date_time' => ($fixtureDate ?? now())->copy()->setTime(20, 0),
        'total_players' => 4,
        'visited_team_id' => $ownTeam->id,
        'visiting_team_id' => $visitingTeam->id,
        'season_id' => $season->id,
        'league_id' => $league->id,
    ]);

    return $visitingTeam->fullName();
}

it('sends a QR payment to the treasurer, waiting to be reconciled', function (): void {
    $order = barOrderToSettle($this->barman, 750);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $order), ['method' => 'qr'])
        ->assertRedirect();

    $payment = Payment::where('payable_type', BarOrder::class)
        ->where('payable_id', $order->id)
        ->sole();

    expect($payment->status)->toBe('pending')
        ->and($payment->amount_due)->toBe(7.5)
        ->and($payment->amount_paid)->toBe(0.0)
        ->and($payment->transaction_id)->toBeNull()
        ->and($payment->payment_method)->toBe('QRCode')
        // Une communication structurée : « Bar order #12 » bloquait des virements.
        ->and($payment->reference)->toMatch('#^\d{3}/\d{4}/\d{5}$#');
});

it('settles an order only once and keeps a single treasury payment', function (): void {
    $order = barOrderToSettle($this->barman, 750);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $order), ['method' => 'qr'])
        ->assertRedirect();

    $payment = $order->payment()->sole();

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $order), ['method' => 'cash'])
        ->assertSessionHas('error', 'Commande déjà payée.');

    expect($order->fresh()->is_paid)->toBe(1)
        ->and($order->fresh()->payment_method)->toBe('qr')
        ->and(Payment::where('payable_type', BarOrder::class)->where('payable_id', $order->id)->count())->toBe(1)
        ->and($payment->fresh()->amount_due)->toBe(7.5);
});

it('rolls back settlement when recording the treasury payment fails', function (): void {
    $order = barOrderToSettle($this->barman, 750);
    $recordPayment = Mockery::mock(RecordBarOrderPayment::class);
    $recordPayment->shouldReceive('__invoke')->once()->andThrow(new RuntimeException('Payment reservation failed.'));
    $this->app->instance(RecordBarOrderPayment::class, $recordPayment);
    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $order), ['method' => 'qr']))
        ->toThrow(RuntimeException::class, 'Payment reservation failed.');

    expect($order->fresh()->is_paid)->toBe(0)
        ->and($order->fresh()->payment_method)->toBeNull()
        ->and(Payment::where('payable_type', BarOrder::class)->where('payable_id', $order->id)->exists())->toBeFalse();
});

it('leaves cash and offered rounds out of the bank list', function (string $method): void {
    $order = barOrderToSettle($this->barman);
    $reason = 'Tournoi du club';

    if ($method === 'offered') {
        $reason = barVisitingTeamForToday();
    }

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $order), ['method' => $method, 'reason' => $reason])
        ->assertRedirect();

    expect($order->fresh()->is_paid)->toBe(1)
        ->and(Payment::where('payable_type', BarOrder::class)->count())->toBe(0);
})->with(['cash', 'offered']);

it('offers a scheduled visiting team only once per business day', function (): void {
    Carbon::setTestNow('2026-09-20 20:00:00');
    $team = barVisitingTeamForToday();
    $first = barOrderToSettle($this->barman);
    $second = barOrderToSettle($this->barman);

    $this->actingAs($this->barman)
        ->get(route('bar.payment.show', $first))
        ->assertOk()
        ->assertSee($team);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $first), ['method' => 'offered', 'reason' => $team])
        ->assertRedirect();

    expect($first->fresh()->reason)->toBe($team);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $second), ['method' => 'offered', 'reason' => $team])
        ->assertSessionHas('error', 'Cette équipe a déjà reçu son verre du club aujourd’hui.');

    expect($second->fresh()->is_paid)->toBe(0);
});

it('hides a visiting team already used for an offered order', function (): void {
    Carbon::setTestNow('2026-09-20 20:00:00');
    $team = barVisitingTeamForToday();
    $first = barOrderToSettle($this->barman);
    $second = barOrderToSettle($this->barman);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $first), ['method' => 'offered', 'reason' => $team])
        ->assertRedirect();

    $this->actingAs($this->barman)
        ->get(route('bar.payment.show', $second))
        ->assertOk()
        ->assertDontSee('value="' . $team . '"', false);
});

it('counts an offered order on the day it is closed, not when it was opened', function (): void {
    Carbon::setTestNow('2026-09-20 20:00:00');
    $team = barVisitingTeamForToday();
    $first = barOrderToSettle($this->barman);
    $second = barOrderToSettle($this->barman);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $first), ['method' => 'offered', 'reason' => $team])
        ->assertRedirect();

    $first->forceFill(['created_at' => '2026-09-19 23:00:00'])->saveQuietly();

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $second), ['method' => 'offered', 'reason' => $team])
        ->assertSessionHas('error', 'Cette équipe a déjà reçu son verre du club aujourd’hui.');

    expect($second->fresh()->is_paid)->toBe(0);
});

it('accepts a manual reason when the visiting team cannot be selected', function (): void {
    $order = barOrderToSettle($this->barman);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $order), [
            'method' => 'offered',
            'manual_reason' => 'Bénévole du club',
        ])
        ->assertRedirect();

    expect($order->fresh()->is_paid)->toBe(1)
        ->and($order->fresh()->reason)->toBe('Bénévole du club');
});

it('keeps the visiting team available when an order is closed later', function (): void {
    Carbon::setTestNow('2026-09-21 10:00:00');
    $team = barVisitingTeamForToday(Carbon::parse('2026-09-20'));
    $order = barOrderToSettle($this->barman);
    $order->created_at = Carbon::parse('2026-09-20 12:00:00');
    $order->save();

    $this->actingAs($this->barman)
        ->get(route('bar.payment.show', $order))
        ->assertOk()
        ->assertSee($team);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $order), ['method' => 'offered', 'reason' => $team])
        ->assertRedirect();

    expect($order->fresh()->reason)->toBe($team);
});

it('rejects an offered order with more than four articles', function (): void {
    Carbon::setTestNow('2026-09-20 20:00:00');
    $team = barVisitingTeamForToday();
    $category = BarCategory::create(['name' => 'Snacks']);
    $product = BarProduct::create([
        'name' => 'Snack',
        'sale_price' => 100,
        'is_available' => true,
        'category_id' => $category->id,
    ]);
    $order = barOrderToSettle($this->barman, 500);
    BarOrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 100,
        'total_price' => 500,
    ]);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $order), ['method' => 'offered', 'reason' => $team])
        ->assertSessionHas('error', 'Un verre du club ne peut pas contenir plus de 4 articles pour cette équipe.');

    expect($order->fresh()->is_paid)->toBe(0);
});

/** Une commande à régler qui porte `$articles` consommations d'un même produit. */
function barOrderWithArticles(User $barman, int $articles): BarOrder
{
    $category = BarCategory::firstOrCreate(['name' => 'Bières']);
    $product = BarProduct::create([
        'name' => 'Jupiler ' . uniqid(),
        'sale_price' => 300,
        'is_available' => true,
        'category_id' => $category->id,
    ]);
    $order = barOrderToSettle($barman, 300 * $articles);
    BarOrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => $articles,
        'unit_price' => 300,
        'total_price' => 300 * $articles,
    ]);

    return $order;
}

it('gives a club round to each visiting team, even two teams of the same club', function (): void {
    // Braine-L'Alleud B et F viennent le même soir : la règle comptait par club, et
    // l'équipe F se voyait refuser le verre que l'équipe B avait déjà reçu.
    Carbon::setTestNow('2026-09-20 20:00:00');
    $teamB = barVisitingTeamForToday(letter: 'B', clubName: "Braine-L'Alleud");
    $teamF = barVisitingTeamForToday(letter: 'F', clubName: "Braine-L'Alleud");
    $first = barOrderToSettle($this->barman);
    $second = barOrderToSettle($this->barman);

    $this->actingAs($this->barman)
        ->get(route('bar.payment.show', $first))
        ->assertOk()
        ->assertSee("Braine-L'Alleud B")
        ->assertSee("Braine-L'Alleud F");

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $first), ['method' => 'offered', 'reason' => $teamB])
        ->assertSessionHas('success');

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $second), ['method' => 'offered', 'reason' => $teamF])
        ->assertSessionHas('success');

    expect($first->fresh()->reason)->toBe("Braine-L'Alleud B")
        ->and($second->fresh()->reason)->toBe("Braine-L'Alleud F");
});

it('caps a club round at three articles for veterans and women', function (string $category): void {
    // Les vétérans et les dames jouent à trois : trois consommations, pas quatre.
    Carbon::setTestNow('2026-09-20 20:00:00');
    $team = barVisitingTeamForToday(category: $category);
    $tooMany = barOrderWithArticles($this->barman, 4);
    $enough = barOrderWithArticles($this->barman, 3);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $tooMany), ['method' => 'offered', 'reason' => $team])
        ->assertSessionHas('error', 'Un verre du club ne peut pas contenir plus de 3 articles pour cette équipe.');

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $enough), ['method' => 'offered', 'reason' => $team])
        ->assertSessionHas('success');

    expect($tooMany->fresh()->is_paid)->toBe(0)
        ->and($enough->fresh()->is_paid)->toBe(1);
})->with(['VETERANS', 'WOMEN']);

it('keeps four articles for a men team and for a beneficiary typed by hand', function (): void {
    Carbon::setTestNow('2026-09-20 20:00:00');
    $team = barVisitingTeamForToday(category: 'MEN');
    $forTeam = barOrderWithArticles($this->barman, 4);
    $byHand = barOrderWithArticles($this->barman, 4);
    $tooManyByHand = barOrderWithArticles($this->barman, 5);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $forTeam), ['method' => 'offered', 'reason' => $team])
        ->assertSessionHas('success');

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $byHand), ['method' => 'offered', 'manual_reason' => 'Arbitre'])
        ->assertSessionHas('success');

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $tooManyByHand), ['method' => 'offered', 'manual_reason' => 'Arbitre'])
        ->assertSessionHas('error', 'Un verre du club ne peut pas contenir plus de 4 articles.');
});

it('describes a bar order in the treasurer list, without inventing a payer', function (): void {
    $order = BarOrder::create([
        'created_by' => $this->barman->id,
        'name' => 'Alpa A',
        'total_price' => 750,
        'is_paid' => 1,
        'paid_at' => now(),
        'payment_method' => 'qr',
    ]);

    $payment = (new RecordBarOrderPayment)($order);

    $rows = Livewire::actingAs($this->treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->viewData('payments');

    $row = collect($rows->items())->firstWhere('reference', $payment->reference);

    expect($row)->not->toBeNull()
        // Le bar ne sait pas qui a payé : le nom de l'ardoise est tout ce qu'on a,
        // et le barman qui a encaissé n'est pas le payeur.
        ->and($row->member)->toBe('Alpa A')
        ->and($row->event_type)->toBe('Bar')
        // La soirée à laquelle la commande appartient : c'est par là que le
        // trésorier retrouve la ligne du relevé.
        ->and($row->event_name)->toBe(now()->translatedFormat('j F Y'))
        // Aucun IBAN : personne n'est nommé, donc rien à rembourser d'ici.
        ->and($row->iban)->toBeNull();
});

/*
 * La recherche de l'écran traverse `payable.user` sur la relation polymorphe.
 * Tous les payables en avaient un jusqu'ici ; une commande de bar n'en a pas.
 * Le risque n'est pas qu'elle rende mal, c'est qu'elle casse pour tout le monde.
 */
it('keeps the treasurer search working once a bar payment sits in the list', function (): void {
    $member = User::factory()->create(['last_name' => 'Vandenbossche']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
    ]);
    $subscription->payments()->create([
        'reference' => '100/2505/00101',
        'amount_due' => 125,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $barOrder = BarOrder::create([
        'created_by' => $this->barman->id,
        'total_price' => 250,
        'is_paid' => 1,
        'paid_at' => now(),
        'payment_method' => 'qr',
    ]);
    (new RecordBarOrderPayment)($barOrder);

    $rows = Livewire::actingAs($this->treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->set('search', 'Vandenbossche')
        ->viewData('payments');

    expect(collect($rows->items())->pluck('reference')->all())->toBe(['100/2505/00101']);
});

it('has nobody to chase for a bar order, and says so instead of failing', function (): void {
    $order = BarOrder::create([
        'created_by' => $this->barman->id,
        'total_price' => 250,
        'is_paid' => 1,
        'paid_at' => now(),
        'payment_method' => 'qr',
    ]);
    $payment = (new RecordBarOrderPayment)($order);

    Livewire::actingAs($this->treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->call('sendReminder', $payment->id);

    expect($payment->fresh()->invitation_counter)->toBe(0)
        ->and($payment->fresh()->last_reminded_at)->toBeNull();
});

/*
 * La reprise remonte l'historique QR déjà encaissé au bar : le trésorier veut
 * pouvoir rattraper le rapprochement des mois passés. Elle est rejouable — un
 * déploiement interrompu ne doit pas laisser la moitié du travail ni, pire,
 * deux lignes à rapprocher pour un seul virement.
 */
it('backfills the QR orders already settled at the counter, and only those', function (): void {
    $qr = BarOrder::create([
        'created_by' => $this->barman->id,
        'total_price' => 400,
        'is_paid' => 1,
        'paid_at' => '2026-05-04 21:30:00',
        'payment_method' => 'qr',
    ]);
    BarOrder::create([
        'created_by' => $this->barman->id,
        'total_price' => 250,
        'is_paid' => 1,
        'paid_at' => '2026-05-04 21:35:00',
        'payment_method' => 'cash',
    ]);
    BarOrder::create([
        'created_by' => $this->barman->id,
        'total_price' => 300,
        'is_paid' => 0,
    ]);

    $this->artisan('bar:backfill-payments')->assertSuccessful();

    expect(Payment::where('payable_type', BarOrder::class)->count())->toBe(1);

    $payment = $qr->fresh()->payment;

    expect($payment->status)->toBe('pending')
        ->and($payment->amount_due)->toBe(4.0)
        // Datée du soir où elle a été encaissée : c'est par la date que le
        // trésorier retrouve la transaction, pas par le jour de la reprise.
        ->and($payment->created_at->toDateString())->toBe('2026-05-04');

    $this->artisan('bar:backfill-payments')->assertSuccessful();

    expect(Payment::where('payable_type', BarOrder::class)->count())->toBe(1);
});

it('lets the treasurer single out what came from the bar', function (): void {
    $member = User::factory()->create(['last_name' => 'Lambert']);
    Subscription::factory()->create(['user_id' => $member->id, 'status' => 'confirmed'])
        ->payments()->create([
            'reference' => '100/2505/00102',
            'amount_due' => 125,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

    $order = BarOrder::create([
        'created_by' => $this->barman->id,
        'total_price' => 250,
        'is_paid' => 1,
        'paid_at' => now(),
        'payment_method' => 'qr',
    ]);
    $payment = (new RecordBarOrderPayment)($order);

    $screen = Livewire::actingAs($this->treasurer)->test('pages::club-admin.treasury.payments');

    // Le filtre doit être proposé, sinon il n'existe que pour les tests.
    expect(collect($screen->viewData('eventTypeOptions'))->pluck('id'))->toContain(BarOrder::class);

    $rows = $screen->set('eventType', BarOrder::class)->viewData('payments');

    expect(collect($rows->items())->pluck('reference')->all())->toBe([$payment->reference]);
});
