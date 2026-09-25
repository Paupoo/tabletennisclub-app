<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/**
 * Le compte à créditer est un numéro, pas une phrase.
 *
 * Le champ « Compte à rembourser » était du texte libre, jamais contrôlé. Une
 * base de démonstration porte déjà un remboursement dont le compte vaut
 * « A rembourser » — quelqu'un a rempli le champ avec son propre libellé. Le
 * trésorier recopie ce que l'écran lui donne : une chaîne qui n'est pas un
 * IBAN part dans sa banque, où elle est refusée au mieux, et au pire rapprochée
 * de rien.
 */
function refundableClaim(): array
{
    $treasurer = User::factory()->create();
    $treasurer->assignRole(Role::TREASURY->value);

    $member = User::factory()->create(['iban' => 'BE68539007547034']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => 120,
    ]);

    return [$treasurer, $subscription->payments()->create([
        'reference' => '611/0926/00001',
        'amount_due' => 120,
        'amount_paid' => 120,
        'status' => 'paid',
        'payment_method' => 'Wire',
    ])];
}

it('refuses an account that is not an account', function (): void {
    [$treasurer, $encashment] = refundableClaim();

    Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->call('openRefundRequest', $encashment->id)
        ->set('refundRequestAmount', 50.0)
        ->set('refundRequestReason', 'Trop-perçu')
        ->set('refundRequestIban', 'A rembourser')
        ->call('confirmRefundRequest')
        ->assertSet('refundRequestModal', true);

    expect(Payment::where('status', 'to_refund')->count())->toBe(0);
})->group('payments', 'refund');

/**
 * Un IBAN se saisit comme on le lit — par groupes de quatre — et se range
 * sous sa forme compacte, celle que l'appariement du virement compare.
 */
it('accepts an account written the way a bank prints it', function (): void {
    [$treasurer, $encashment] = refundableClaim();

    Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->call('openRefundRequest', $encashment->id)
        ->set('refundRequestAmount', 50.0)
        ->set('refundRequestReason', 'Trop-perçu')
        ->set('refundRequestIban', 'BE62 5100 0754 7061')
        ->call('confirmRefundRequest')
        ->assertSet('refundRequestModal', false);

    expect(Payment::where('status', 'to_refund')->sole()->refund_iban)->toBe('BE62510007547061');
})->group('payments', 'refund');
