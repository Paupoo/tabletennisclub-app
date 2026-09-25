<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/**
 * Ce que le club détient en trop, net de ce qu'il a déjà rendu.
 *
 * L'excédent se calculait sur la seule ligne d'encaissement — `payé − dû` —
 * alors qu'un remboursement est une ligne à part. Rendre l'argent ne diminuait
 * donc jamais le trop-perçu : après avoir viré 200 € sur 650, l'écran en
 * annonçait toujours 650, et le trésorier pouvait rembourser deux fois.
 *
 * Les remboursements déjà versés comptent, et ceux qui sont seulement ouverts
 * aussi : entre l'ouverture et le virement, l'argent est déjà promis.
 */
function memberHoldingAnExcess(float $due, float $received): array
{
    $treasurer = User::factory()->create();
    $treasurer->assignRole(Role::TREASURY->value);

    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create(['iban' => 'BE68539007547034'])->id,
        'status' => 'confirmed',
        'amount_due' => $due,
    ]);

    $subscription->payments()->create([
        'reference' => '820/0926/00001',
        'amount_due' => $due,
        'amount_paid' => $received,
        'status' => 'paid',
        'payment_method' => 'Wire',
    ]);

    return [$treasurer, $subscription];
}

it('subtracts what has already been given back from the excess', function (): void {
    [$treasurer, $subscription] = memberHoldingAnExcess(due: 170, received: 820);

    // 200 € déjà virés et rapprochés.
    $subscription->payments()->create([
        'reference' => '820/0926/00002',
        'amount_due' => 200,
        'amount_paid' => 200,
        'status' => 'refunded',
        'payment_method' => 'refund',
    ]);

    Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->set('statusFilter', 'overpaid')
        ->assertSee('450,00 €')
        ->assertDontSee('650,00 €');
})->group('payments', 'overpaid');

/**
 * Un remboursement ouvert mais pas encore viré retient déjà l'argent : sans
 * cela, le trésorier rouvrirait une seconde demande pour la même somme.
 */
it('counts a refund already opened, even before the money leaves', function (): void {
    [$treasurer, $subscription] = memberHoldingAnExcess(due: 170, received: 820);

    $subscription->payments()->create([
        'reference' => '820/0926/00003',
        'amount_due' => 200,
        'amount_paid' => 0,
        'status' => 'to_refund',
        'payment_method' => 'refund',
    ]);

    Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->set('statusFilter', 'overpaid')
        ->assertSee('450,00 €')
        ->assertDontSee('650,00 €');
})->group('payments', 'overpaid');
