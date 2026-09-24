<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\FamilyGroup;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use Livewire\Livewire;

/*
| `openPaymentModal` prenait n'importe quel identifiant de paiement : un membre
| pouvait afficher la communication, le montant et le QR d'un autre en
| rejouant l'appel Livewire. Le périmètre est celui que l'écran montre — le
| membre et sa famille — recalculé côté serveur.
*/

beforeEach(function (): void {
    makeActiveSeason();
    Club::factory()->ownClub()->create(['bic' => 'GEBABEBB', 'bank_account' => 'BE68539007547034']);
    Club::forgetOwnClub();
});

function pendingSubscriptionPayment(User $user): Payment
{
    $subscription = Subscription::factory()->create(['user_id' => $user->id, 'status' => 'confirmed']);

    return $subscription->payments()->create([
        'reference' => '900/0000/' . fake()->unique()->numberBetween(10000, 99999),
        'amount_due' => 125,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);
}

it('refuses to open the payment of someone outside the family', function (): void {
    $member = User::factory()->create();
    $stranger = User::factory()->create();
    $payment = pendingSubscriptionPayment($stranger);

    $screen = Livewire::actingAs($member)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $member]);

    // Ni en se donnant pour l'autre, ni en gardant son propre identifiant.
    $screen->call('openPaymentModal', $stranger->id, $payment->id)
        ->assertSet('paymentModal', false)
        ->assertSet('paymentDetails', [])
        ->call('openPaymentModal', $member->id, $payment->id)
        ->assertSet('paymentModal', false)
        ->assertSet('paymentDetails', []);
});

it('opens the payment of a family member', function (): void {
    $parent = User::factory()->create();
    $child = User::factory()->create();
    FamilyGroup::factory()->create()->users()->attach([$parent->id, $child->id]);

    $payment = pendingSubscriptionPayment($child);

    Livewire::actingAs($parent)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $parent])
        ->call('openPaymentModal', $child->id, $payment->id)
        ->assertSet('paymentModal', true)
        ->assertSet('paymentDetails.reference', $payment->reference);
});

it('opens the members own payment', function (): void {
    $member = User::factory()->create();
    $payment = pendingSubscriptionPayment($member);

    Livewire::actingAs($member)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $member])
        ->call('openPaymentModal', $member->id, $payment->id)
        ->assertSet('paymentModal', true);
});
