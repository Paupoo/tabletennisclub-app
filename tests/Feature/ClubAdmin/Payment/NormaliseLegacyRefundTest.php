<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\DB;

function runNormaliseLegacyRefundMigration(): void
{
    $migration = require base_path('database/migrations/2026_09_25_101831_normalise_legacy_refund_payments.php');
    $migration->up();
}

/**
 * Pose une ligne telle que l'ancien seeder la laissait : un paiement bel et
 * bien encaissé, dont on a simplement basculé le statut.
 */
function flippedEncashment(Subscription $subscription): Payment
{
    $payment = $subscription->payments()->create([
        'reference' => '090/0926/00001',
        'amount_due' => 120,
        'amount_paid' => 120,
        'status' => 'to_refund',
        'payment_method' => 'Wire',
    ]);

    DB::table('payments')->where('id', $payment->id)->update(['transaction_id' => '4']);

    return $payment->fresh();
}

/**
 * Un encaissement basculé se sépare en deux faits distincts.
 *
 * L'argent est entré — cela reste vrai, et la ligne qui le dit doit rester
 * `paid` avec son crédit. Ce que le club doit rendre est un autre fait, qui
 * mérite sa propre ligne : sans elle, `amount_paid` compte ce qui est entré là
 * où le reste du domaine y lit ce qui est sorti, le solde vaut zéro, et
 * l'exécution du remboursement se termine sur « il ne reste rien à affecter ».
 */
it('splits a flipped encashment into what came in and what is owed back', function (): void {
    $member = User::factory()->create(['iban' => 'BE68539007547034']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => 120,
    ]);

    $encashment = flippedEncashment($subscription);

    runNormaliseLegacyRefundMigration();

    expect($encashment->fresh()->status)->toBe('paid');

    $refund = Payment::where('payment_method', 'refund')->where('status', 'to_refund')->sole();

    expect((float) $refund->amount_due)->toBe(120.0)
        ->and((float) $refund->amount_paid)->toBe(0.0)
        ->and($refund->transaction_id)->toBeNull()
        ->and($refund->refund_iban)->toBe('BE68539007547034')
        ->and($refund->payable_id)->toBe($subscription->id);
})->group('payments', 'refund');

/**
 * Un remboursement ouvert dans les règles n'est pas une scission à défaire.
 *
 * `down()` ne peut pas distinguer ce que cette migration a créé d'un
 * remboursement ouvert par l'action : les deux ont la forme canonique et aucun
 * encaissement porté. S'il devine sur l'appariement des montants, il détruit
 * une dette réelle du club envers un membre — et remet en « à rembourser » une
 * cotisation qui a été reçue.
 */
it('refuses to undo rather than guess which refund it created', function (): void {
    $member = User::factory()->create(['iban' => 'BE68539007547034']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => 120,
    ]);

    // Une cotisation reçue, rapprochée de son virement.
    $encashment = $subscription->payments()->create([
        'reference' => '090/0926/00002',
        'amount_due' => 120,
        'amount_paid' => 120,
        'status' => 'paid',
        'payment_method' => 'Wire',
    ]);
    DB::table('payments')->where('id', $encashment->id)->update(['transaction_id' => '7']);

    // Et un remboursement ouvert par l'action, du même montant.
    $refund = $subscription->payments()->create([
        'reference' => '090/0926/00003',
        'amount_due' => 120,
        'amount_paid' => 0,
        'status' => 'to_refund',
        'payment_method' => 'refund',
        'refund_iban' => 'BE68539007547034',
    ]);

    $migration = require base_path('database/migrations/2026_09_25_101831_normalise_legacy_refund_payments.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);

    expect(Payment::find($refund->id))->not->toBeNull()
        ->and($encashment->fresh()->status)->toBe('paid');
})->group('payments', 'refund');
