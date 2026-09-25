<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\RequestSubscriptionRefundAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use Database\Seeders\TreasurySeeder;

/**
 * Un remboursement est une ligne à lui, jamais un encaissement déguisé.
 *
 * `to_refund` a longtemps recouvert deux formes incompatibles : la ligne dédiée
 * que crée {@see RequestSubscriptionRefundAction},
 * et un paiement déjà encaissé dont on bascule le statut. Sur la seconde,
 * `amount_paid` compte ce qui est **entré** ; sur la première, ce qui est
 * **sorti**. Aucun écran ne peut afficher un chiffre juste pour les deux, et
 * l'exécution du remboursement se termine en cul-de-sac : le solde vaut zéro,
 * et l'affectation est refusée faute de quoi que ce soit à affecter.
 *
 * Cette invariante ferme la seconde forme.
 */
it('seeds every refund as a dedicated line, never as a flipped encashment', function (): void {
    User::factory()->create(['email' => 'gilles.herpigny@test.com']);
    Season::factory()->create();

    test()->seed(TreasurySeeder::class);

    $awaiting = Payment::where('status', 'to_refund')->get();

    expect($awaiting)->not->toBeEmpty();

    foreach ($awaiting as $refund) {
        expect($refund->payment_method)->toBe('refund')
            ->and($refund->transaction_id)->toBeNull();
    }
})->group('payments', 'refund');
