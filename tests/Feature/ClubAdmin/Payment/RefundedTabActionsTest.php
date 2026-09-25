<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/**
 * Une ligne déjà remboursée n'offre plus rien à faire.
 *
 * L'onglet « Remboursé » tombait dans la branche des créances réglées : chaque
 * ligne y portait une pastille verte « Payé » — alors que c'est de l'argent
 * **sorti** — et un bouton « Rembourser ». Celui-ci ouvrait une demande sur la
 * même affiliation, préremplie à 0 €, que la confirmation refusait ensuite : un
 * cul-de-sac proposé au milieu d'un écran comptable.
 *
 * Ce que la ligne a à dire tient déjà dans sa colonne d'état : remboursé, et
 * viré tel jour.
 */
function refundedClaim(): array
{
    $treasurer = User::factory()->create();
    $treasurer->assignRole(Role::TREASURY->value);

    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create(['iban' => 'BE68539007547034'])->id,
        'status' => 'confirmed',
        'amount_due' => 120,
    ]);

    $encashment = $subscription->payments()->create([
        'reference' => '440/0926/00001',
        'amount_due' => 120,
        'amount_paid' => 120,
        'status' => 'paid',
        'payment_method' => 'Wire',
    ]);

    $refund = $subscription->payments()->create([
        'reference' => '440/0926/00002',
        'amount_due' => 120,
        'amount_paid' => 120,
        'status' => 'refunded',
        'payment_method' => 'refund',
    ]);

    return [$treasurer, $encashment, $refund];
}

it('offers nothing to do on a refund that is already done', function (): void {
    [$treasurer, , $refund] = refundedClaim();

    Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->set('statusFilter', 'refunded')
        ->assertSee($refund->reference)
        ->assertDontSeeHtml('openRefundRequest(' . $refund->id . ')');
})->group('payments', 'refund');

/**
 * Sur une créance réglée, en revanche, le geste garde son sens : le membre
 * appelle, le trésorier ouvre.
 */
it('still lets the treasurer open a refund on a settled claim', function (): void {
    [$treasurer, $encashment] = refundedClaim();

    Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->set('statusFilter', 'paid')
        ->assertSee($encashment->reference)
        ->assertSeeHtml('openRefundRequest(' . $encashment->id . ')');
})->group('payments', 'refund');
