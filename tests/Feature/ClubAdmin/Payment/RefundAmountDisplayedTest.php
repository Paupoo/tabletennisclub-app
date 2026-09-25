<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\RequestSubscriptionRefundAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/**
 * L'onglet « À rembourser » n'affiche jamais zéro.
 *
 * Le tableau montre le solde — ce qu'il reste à virer. La modale d'exécution
 * montrait `amount_paid`, c'est-à-dire ce qui est **déjà sorti** : zéro, par
 * construction, tant que le virement n'a pas été fait. Le trésorier ouvrait
 * donc une modale intitulée « Confirmer le remboursement » qui annonçait
 * 0,00 € à rembourser.
 *
 * L'écran est peuplé pour qu'aucun zéro ne soit légitime : une créance en
 * attente, un paiement reçu, un remboursement ouvert. Toute somme nulle qui
 * apparaît est donc un chiffre faux, sans que l'assertion ait à connaître le
 * balisage.
 */
it('never shows a zero amount on the refund tab', function (): void {
    $treasurer = User::factory()->create();
    $treasurer->assignRole(Role::TREASURY->value);

    $member = User::factory()->create(['iban' => 'BE68539007547034']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => 203.45,
    ]);

    // De quoi rendre les trois cartes de statistiques non nulles.
    $subscription->payments()->create([
        'reference' => '451/0926/00001',
        'amount_due' => 203.45,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);
    $subscription->payments()->create([
        'reference' => '451/0926/00002',
        'amount_due' => 91.20,
        'amount_paid' => 91.20,
        'status' => 'paid',
    ]);

    // Et de quoi remplir la quatrième : sans elle, la carte « Trop-perçus »
    // affiche un zéro parfaitement légitime, et l'assertion ci-dessous ne
    // saurait plus distinguer un total vide d'un chiffre faux.
    //
    // Sur une **autre** affiliation : l'excédent est net des remboursements
    // ouverts sur la même chose payée, et celui d'en dessous l'effacerait.
    $other = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'amount_due' => 40,
    ]);
    $other->payments()->create([
        'reference' => '451/0926/00003',
        'amount_due' => 40,
        'amount_paid' => 61.30,
        'status' => 'paid',
        'payment_method' => 'Wire',
    ]);

    // Et la cinquième. Ce test exige que toute carte porte un chiffre : c'est
    // ce qui permet à l'assertion finale de ne rien savoir du balisage, et le
    // prix à payer est d'alimenter chaque nouvel état ajouté à l'écran.
    // Sur une troisième affiliation, pour la même raison : posé sur celle du
    // trop-perçu, il l'effacerait — l'excédent est net des remboursements.
    $settled = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'amount_due' => 88.15,
    ]);
    $settled->payments()->create([
        'reference' => '451/0926/00004',
        'amount_due' => 88.15,
        'amount_paid' => 88.15,
        'status' => 'refunded',
        'payment_method' => 'refund',
    ]);

    $refund = (new RequestSubscriptionRefundAction)($subscription, 137.50, 'Trop-perçu');

    Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->set('statusFilter', 'to_refund')
        ->call('openRefundReconcile', $refund->id)
        ->assertSet('refundModal', true)
        ->assertSee('137,50 €')
        ->assertDontSee('0,00 €');
})->group('payments', 'refund');
