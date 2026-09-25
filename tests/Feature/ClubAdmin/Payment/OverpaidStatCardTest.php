<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/**
 * Quatre onglets, quatre cartes.
 *
 * « Trop-perçu » est arrivé comme quatrième onglet sans sa carte : la rangée en
 * comptait trois, et l'argent que le club détient en trop était le seul état à
 * n'avoir aucun total lisible d'un coup d'œil. C'est pourtant celui qui appelle
 * une action — cet argent ne lui appartient plus.
 */
it('totals the money the club is holding in excess', function (): void {
    $treasurer = User::factory()->create();
    $treasurer->assignRole(Role::TREASURY->value);

    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'amount_due' => 60,
    ]);

    // 137,45 reçus pour 60 dus : 77,45 en trop. Le montant est choisi pour
    // n'être la sous-chaîne d'aucun autre total de l'écran — « 37,45 » se
    // serait trouvé dans le « 137,45 € » de la carte « Payé ».
    $subscription->payments()->create([
        'reference' => '778/0926/00001',
        'amount_due' => 60,
        'amount_paid' => 137.45,
        'status' => 'paid',
        'payment_method' => 'Wire',
    ]);

    Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->assertSee('77,45 €');
})->group('payments');

/**
 * Une carte informe, un onglet filtre — DS-A.
 *
 * Les cartes portaient un anneau quand l'onglet correspondant était actif :
 * le vocabulaire visuel d'un contrôle sélectionné, sur un élément qui ne
 * réagit à rien. Le composant `stat-card` tranche lui-même la question dans sa
 * documentation — « purement informatif : le filtrage passe par les onglets,
 * jamais par la carte » — et c'est l'écran qui le contredisait.
 *
 * Vérifié sur la source, comme DS-B l'est par TextSizeFloorTest : un état
 * « sélectionné » ne se lit pas dans le DOM rendu sans savoir quel onglet est
 * actif, alors qu'il se lit d'un coup d'œil dans le gabarit.
 */
it('never dresses a stat card as the selected control', function (): void {
    $blade = file_get_contents(
        resource_path('views/pages/club-admin/treasury/⚡payments/payments.blade.php')
    );

    expect($blade)->not->toContain('ring-2 ring-primary');
})->group('payments');
