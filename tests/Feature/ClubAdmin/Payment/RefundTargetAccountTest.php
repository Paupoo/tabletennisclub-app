<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\RequestSubscriptionRefundAction;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;

/**
 * Un remboursement sait vers quel compte il part.
 *
 * Un trop-perçu ne se rend pas au membre, il se rend **au compte qui a versé**
 * — souvent celui d'un tuteur, parfois d'un grand-parent ou d'un employeur.
 * L'appariement du virement sortant comparait l'IBAN du membre : pour ces
 * cas-là il n'aurait jamais rien reconnu.
 */
it('remembers the account a refund must reach', function (): void {
    $member = User::factory()->create(['iban' => 'BE68539007547034']);

    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => 120,
    ]);

    $refund = (new RequestSubscriptionRefundAction)(
        $subscription,
        100.0,
        'Trop-perçu',
        // Le compte du tuteur, qui a payé pour l'enfant.
        targetIban: 'BE62510007547061',
    );

    expect($refund->refund_iban)->toBe('BE62510007547061')
        ->and($refund->refund_iban)->not->toBe($member->iban);
})->group('payments', 'refund');

/**
 * Sans précision, on rend au membre : c'est le cas courant d'une cotisation
 * annulée, et on ne change pas ce qui marchait.
 */
it('falls back to the member account when no target is given', function (): void {
    $member = User::factory()->create(['iban' => 'BE68539007547034']);

    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => 120,
    ]);

    $refund = (new RequestSubscriptionRefundAction)($subscription, 100.0, 'Pack arrêté par le club');

    expect($refund->refund_iban)->toBe('BE68539007547034');
})->group('payments', 'refund');

/**
 * Le virement sortant se reconnaît sur le compte visé, pas sur celui du membre.
 *
 * `previewBatchRefundMatch` comparait `$user->iban`. Un remboursement vers le
 * compte d'un tuteur n'aurait jamais été apparié, et le trésorier aurait dû le
 * rapprocher à la main sans savoir pourquoi l'outil restait muet.
 */
it('matches an outgoing transfer on the account the refund targets', function (): void {
    $member = User::factory()->create(['iban' => 'BE68539007547034']);

    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => 120,
    ]);

    (new RequestSubscriptionRefundAction)(
        $subscription,
        100.0,
        'Trop-perçu',
        targetIban: 'BE62510007547061',
    );

    // Le club vire vers le compte du tuteur, pas vers celui de l'enfant.
    Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EN FAVEUR DE TIERS',
        'amount' => -100.0,
        'counterparty_name' => 'Sophie Martin',
        'counterparty_bank_account' => 'BE62510007547061',
    ]);

    $matches = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-admin.treasury.payments')
        ->call('previewBatchRefundMatch')
        ->get('refundBatchMatches');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['amount'])->toBe(100.0);
})->group('payments', 'refund');
