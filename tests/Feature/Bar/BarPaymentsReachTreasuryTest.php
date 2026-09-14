<?php

declare(strict_types=1);

use App\Actions\Bar\RecordBarOrderPayment;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
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
    Club::factory()->ownClub()->create();
    $this->barman = User::factory()->isAdmin()->create();
    $this->treasurer = User::factory()->isAdmin()->create();
});

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
        // La communication que le client a sous les yeux en scannant le QR.
        ->and($payment->reference)->toBe("Bar order #{$order->id}");
});

it('leaves cash and offered rounds out of the bank list', function (string $method): void {
    $order = barOrderToSettle($this->barman);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $order), ['method' => $method, 'reason' => 'Tournoi du club'])
        ->assertRedirect();

    expect($order->fresh()->is_paid)->toBe(1)
        ->and(Payment::where('payable_type', BarOrder::class)->count())->toBe(0);
})->with(['cash', 'offered']);

it('describes a bar order in the treasurer list, without inventing a payer', function (): void {
    $order = BarOrder::create([
        'created_by' => $this->barman->id,
        'name' => 'Alpa A',
        'total_price' => 750,
        'is_paid' => 1,
        'paid_at' => now(),
        'payment_method' => 'qr',
    ]);

    (new RecordBarOrderPayment)($order);

    $rows = Livewire::actingAs($this->treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->viewData('payments');

    $row = collect($rows->items())->firstWhere('reference', "Bar order #{$order->id}");

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
    (new RecordBarOrderPayment)($order);

    $screen = Livewire::actingAs($this->treasurer)->test('pages::club-admin.treasury.payments');

    // Le filtre doit être proposé, sinon il n'existe que pour les tests.
    expect(collect($screen->viewData('eventTypeOptions'))->pluck('id'))->toContain(BarOrder::class);

    $rows = $screen->set('eventType', BarOrder::class)->viewData('payments');

    expect(collect($rows->items())->pluck('reference')->all())->toBe(["Bar order #{$order->id}"]);
});
