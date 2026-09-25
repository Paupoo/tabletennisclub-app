<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/**
 * Trier sur « Montant » range ce que la colonne montre.
 *
 * L'en-tête est déclaré sur `amount_due`, et le tri porte sur cette clé. La
 * cellule, elle, affiche le solde — depuis qu'une créance se crédite en
 * plusieurs fois, les deux divergent. Cliquer l'en-tête produisait donc un
 * ordre qui ne correspondait à aucun chiffre visible, et d'autant plus faux que
 * le club encaisse des acomptes.
 *
 * Deux créances suffisent à le montrer : leur ordre par montant réclamé est
 * l'inverse de leur ordre par solde restant.
 */
it('orders the amount column by the figure it displays', function (): void {
    $treasurer = User::factory()->create();
    $treasurer->assignRole(Role::TREASURY->value);

    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'amount_due' => 400,
    ]);

    // Réclamée 300, presque soldée : il ne reste que 12.
    $almostSettled = $subscription->payments()->create([
        'reference' => 'AAA/0926/00001',
        'amount_due' => 300,
        'amount_paid' => 288,
        'status' => 'pending',
    ]);

    // Réclamée 80, intacte : il reste 80.
    $untouched = $subscription->payments()->create([
        'reference' => 'ZZZ/0926/00002',
        'amount_due' => 80,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $html = Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->set('sortBy', ['column' => 'amount_due', 'direction' => 'asc'])
        ->html();

    // Sur les positions dans le HTML rendu, et non par `assertSeeInOrder` :
    // celui-ci est transmis tel quel à la réponse Livewire, où le gabarit est
    // encodé en JSON — la référence y apparaît « AAA\/0926\/… » et n'est
    // jamais trouvée.
    expect(strpos($html, $almostSettled->reference))
        ->toBeLessThan(strpos($html, $untouched->reference));
})->group('payments');
