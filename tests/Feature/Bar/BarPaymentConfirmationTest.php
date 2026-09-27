<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;

/*
|--------------------------------------------------------------------------
| Bar — encaisser sans se tromper de mode (retour de Jean, 2026-09-27)
|--------------------------------------------------------------------------
|
| Un tap sur « Cash » au lieu de « QR Code » réglait la commande sans retour
| possible. Le cash passe donc par une confirmation, comme le QR. Et le QR porte
| désormais une communication structurée : « Bar order #12 » bloquait certains
| virements, le « # » étant refusé par des banques. La ligne du trésorier naît
| dès l'affichage du QR, pour réserver cette communication et pour qu'un
| virement fait sans « Paiement reçu » trouve quand même sa ligne.
|
*/

beforeEach(function (): void {
    $this->travelTo(now()->setTime(14, 0));

    Club::factory()->ownClub()->create();
    $this->barman = User::factory()->isAdmin()->create();
});

/** Une commande servie, prête à être encaissée. */
function barPaymentConfirmationOrder(User $barman, int $cents = 750): BarOrder
{
    return BarOrder::create(['created_by' => $barman->id, 'total_price' => $cents, 'is_paid' => 0]);
}

/** La ligne du trésorier d'une commande, s'il y en a une. */
function barPaymentConfirmationLine(BarOrder $order): ?Payment
{
    return Payment::query()->where('payable_type', $order->getMorphClass())->where('payable_id', $order->id)->first();
}

it('reserves a structured communication, waiting to be reconciled, as soon as the QR shows', function (): void {
    $order = barPaymentConfirmationOrder($this->barman);

    $this->actingAs($this->barman)
        ->get(route('bar.payment.show', ['order' => $order->id, 'method' => 'qr']))
        ->assertOk();

    $line = barPaymentConfirmationLine($order);

    expect($line)->not->toBeNull()
        ->and($line->status)->toBe('pending')
        ->and($line->amount_due)->toBe(7.5)
        ->and($line->payment_method)->toBe('QRCode')
        ->and($line->reference)->toMatch('#^\d{3}/\d{4}/\d{5}$#');

    // La communication structurée belge : les deux derniers chiffres sont le reste
    // modulo 97 des dix premiers, 97 quand le reste est nul.
    $digits = str_replace('/', '', $line->reference);
    $remainder = (int) substr($digits, 0, 10) % 97;
    expect((int) substr($digits, 10))->toBe($remainder === 0 ? 97 : $remainder);
});

it('gives the same communication when the QR opens again, at the amount of the moment', function (): void {
    $order = barPaymentConfirmationOrder($this->barman);
    $qr = route('bar.payment.show', ['order' => $order->id, 'method' => 'qr']);

    $this->actingAs($this->barman)->get($qr)->assertOk();
    $reference = barPaymentConfirmationLine($order)->reference;

    $order->update(['total_price' => 1000]);
    $this->actingAs($this->barman)->get($qr)->assertOk();

    expect(Payment::query()->where('payable_id', $order->id)->count())->toBe(1)
        ->and(barPaymentConfirmationLine($order))
        ->reference->toBe($reference)
        ->amount_due->toBe(10.0);
});

it('settles the order on the reserved line when the payment is received', function (): void {
    $order = barPaymentConfirmationOrder($this->barman);

    $this->actingAs($this->barman)->get(route('bar.payment.show', ['order' => $order->id, 'method' => 'qr']));
    $reference = barPaymentConfirmationLine($order)->reference;

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $order), ['method' => 'qr'])
        ->assertRedirect();

    expect($order->fresh()->is_paid)->toBeTruthy()
        ->and(Payment::query()->where('payable_id', $order->id)->count())->toBe(1)
        ->and(barPaymentConfirmationLine($order)->reference)->toBe($reference);
});

it('frees the reserved line when the order is settled another way', function (array $settlement): void {
    $order = barPaymentConfirmationOrder($this->barman);

    $this->actingAs($this->barman)->get(route('bar.payment.show', ['order' => $order->id, 'method' => 'qr']));

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $order), $settlement)
        ->assertRedirect(route('bar.orders.index'));

    expect($order->fresh()->is_paid)->toBeTruthy()
        ->and(barPaymentConfirmationLine($order))->toBeNull();
})->with([
    'cash' => [['method' => 'cash']],
    'offered' => [['method' => 'offered', 'manual_reason' => 'Bénévole']],
]);

it('keeps a line the treasurer already reconciled, whatever the counter does next', function (): void {
    $order = barPaymentConfirmationOrder($this->barman);

    $this->actingAs($this->barman)->get(route('bar.payment.show', ['order' => $order->id, 'method' => 'qr']));
    barPaymentConfirmationLine($order)->update(['status' => 'paid', 'amount_paid' => 7.5]);

    $this->actingAs($this->barman)->post(route('bar.payment.pay', $order), ['method' => 'cash']);

    expect(barPaymentConfirmationLine($order))->not->toBeNull();
});

it('frees the reserved line when the order is deleted', function (): void {
    $order = barPaymentConfirmationOrder($this->barman);

    $this->actingAs($this->barman)->get(route('bar.payment.show', ['order' => $order->id, 'method' => 'qr']));

    $this->actingAs($this->barman)
        ->delete(route('bar.orders.destroy', $order))
        ->assertRedirect(route('bar.orders.index'));

    expect(Payment::query()->where('payable_type', $order->getMorphClass())->where('payable_id', $order->id)->exists())->toBeFalse();
});

/** Le contenu d'une modale de la page, découpé à son `<dialog>`. */
function barPaymentConfirmationDialog(string $html, string $id): string
{
    $start = strpos($html, 'id="' . $id . '"');
    expect($start)->not->toBeFalse("La modale {$id} doit exister.");

    return substr($html, $start, strpos($html, '</dialog>', $start) - $start);
}

it('asks to confirm a cash payment, naming the mode and the amount, with a way to the QR', function (): void {
    $order = barPaymentConfirmationOrder($this->barman);

    $html = $this->actingAs($this->barman)
        ->get(route('bar.payment.show', $order))
        ->assertOk()
        ->getContent();

    $cash = barPaymentConfirmationDialog($html, 'bar-cash-modal');

    // Le seul formulaire qui règle en cash vit dans la modale : un tap sur
    // « Cash » ne peut plus rien enregistrer à lui seul.
    expect(substr_count($html, 'name="method" value="cash"'))->toBe(1)
        ->and($cash)->toContain('name="method" value="cash"')
        ->toContain('Payer en cash')
        ->toContain(euros(750))
        ->toContain(e(route('bar.payment.show', ['order' => $order->id, 'method' => 'qr'])))
        // Afficher la page ne réserve rien : seul le QR le fait.
        ->and(barPaymentConfirmationLine($order))->toBeNull();
});

it('spells out the transfer in the QR modal, for a bank app that cannot read the code', function (): void {
    $order = barPaymentConfirmationOrder($this->barman);

    $html = $this->actingAs($this->barman)
        ->get(route('bar.payment.show', ['order' => $order->id, 'method' => 'qr']))
        ->assertOk()
        ->getContent();

    $qr = barPaymentConfirmationDialog($html, 'bar-qr-modal');

    expect($qr)->toContain('Virement manuel')
        ->toContain('C.T.T Ottignies-Blocry')
        ->toContain('BE23 7323 3320 8791')
        ->toContain(euros(750))
        ->toContain('+++' . barPaymentConfirmationLine($order)->reference . '+++');
});

it('names our own club in the transfer, even on a night a visiting club is expected', function (): void {
    // La liste des clubs visiteurs de l'offert est rendue plus haut sur la même
    // page : un nom de variable partagé y remplaçait notre club par le visiteur.
    $season = Season::factory()->create(['is_active' => true]);
    $league = League::create(['division' => '1A', 'level' => 'PROVINCIAL_BW', 'category' => 'MEN', 'season_id' => $season->id]);
    $team = fn (Club $club, string $name): Team => Team::create(['name' => $name, 'season_id' => $season->id, 'league_id' => $league->id, 'club_id' => $club->id]);
    Interclub::create([
        'address' => 'Clubhouse',
        'start_date_time' => now()->setTime(20, 0),
        'total_players' => 4,
        'visited_team_id' => $team(Club::query()->where('is_own_club', true)->sole(), 'A')->id,
        'visiting_team_id' => $team(Club::factory()->create(['name' => 'Visiting Club']), 'B')->id,
        'season_id' => $season->id,
        'league_id' => $league->id,
    ]);
    $order = barPaymentConfirmationOrder($this->barman);

    $html = $this->actingAs($this->barman)
        ->get(route('bar.payment.show', ['order' => $order->id, 'method' => 'qr']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Visiting Club')
        ->and(barPaymentConfirmationDialog($html, 'bar-qr-modal'))
        ->toContain('C.T.T Ottignies-Blocry')
        ->toContain('BE23 7323 3320 8791');
});
