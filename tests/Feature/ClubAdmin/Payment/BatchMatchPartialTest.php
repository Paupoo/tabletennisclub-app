<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/**
 * Deux virements sur une même créance ne sont jamais « le montant exact ».
 *
 * Un membre qui paie en deux fois reprend la même communication structurée. La
 * passe en masse répartit correctement — 36 puis 24 sur une créance de 60 —
 * mais le verdict se calculait sur le solde **déjà entamé par la passe
 * elle-même** : arrivé au second virement, il restait 24 face à une ligne de
 * 24, et l'appariement annonçait « communication et montant exacts » en
 * cochant d'office. Le trésorier lisait « exact » sous un montant qui n'est pas
 * celui de la créance.
 *
 * Les deux gardes voisines regardaient pourtant la base — créance intacte,
 * virement intact — et non ce que la boucle venait de consommer.
 */
it('never calls a second transfer on the same reference an exact match', function (): void {
    $treasurer = User::factory()->create();
    $treasurer->assignRole(Role::TREASURY->value);

    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create(['first_name' => 'Finn', 'last_name' => 'Martin'])->id,
        'status' => 'confirmed',
        'amount_due' => 60,
    ]);

    $claim = $subscription->payments()->create([
        'reference' => '025/0926/00297',
        'amount_due' => 60,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    foreach ([['2026-08-30', 36.0], ['2026-08-31', 24.0]] as [$date, $amount]) {
        Transaction::create([
            'date' => $date,
            'description' => 'VIREMENT',
            'amount' => $amount,
            'counterparty_name' => 'Finn Martin',
            'structured_reference' => $claim->reference,
        ]);
    }

    $screen = Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->call('previewBatchMatch');

    $matches = $screen->get('batchMatches');

    expect($matches)->toHaveCount(2)
        ->and(collect($matches)->pluck('exact')->all())->toBe([false, false])
        ->and($screen->get('selectedBatchMatches'))->toBe([]);

    // Chaque ligne dit sa part de la créance, jamais une dette qui décroît :
    // « 60 € dus » puis « 24 € dus » s'additionnent à l'œil et laissent croire
    // qu'il en faut 84.
    expect($matches[0]['reason'])->toBe(__(':taken € of :due €', ['taken' => '36,00', 'due' => '60,00']))
        ->and($matches[1]['reason'])->toBe(__(':taken € of :due €', ['taken' => '24,00', 'due' => '60,00']));
})->group('payments');

/**
 * Quand les virements ne couvrent pas la créance, la dernière ligne le dit.
 *
 * Sans cela, deux montants qui ne font pas le compte se lisent comme s'ils le
 * faisaient : c'est la seule chose que la liste ne peut pas laisser deviner.
 */
it('says what will remain when the transfers do not cover the claim', function (): void {
    $treasurer = User::factory()->create();
    $treasurer->assignRole(Role::TREASURY->value);

    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'amount_due' => 60,
    ]);

    $claim = $subscription->payments()->create([
        'reference' => '025/0926/00298',
        'amount_due' => 60,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    Transaction::create([
        'date' => '2026-08-30',
        'description' => 'VIREMENT',
        'amount' => 36.0,
        'counterparty_name' => 'Finn Martin',
        'structured_reference' => $claim->reference,
    ]);

    $matches = Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->call('previewBatchMatch')
        ->get('batchMatches');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['reason'])->toBe(__(':taken € of :due € — :left € will remain', [
            'taken' => '36,00',
            'due' => '60,00',
            'left' => '24,00',
        ]));
})->group('payments');
