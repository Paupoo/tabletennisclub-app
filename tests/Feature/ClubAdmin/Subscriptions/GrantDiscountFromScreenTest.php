<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const REGISTRATIONS_COMPONENT = 'pages::club-admin.users.registrations';

function discountScreen(): Testable
{
    return Livewire::actingAs(User::factory()->isAdmin()->create())->test(REGISTRATIONS_COMPONENT);
}

function affiliationToDiscount(bool $competitive = true): Subscription
{
    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'is_competitive' => $competitive,
    ]);

    (new CalculatePriceAction)($subscription);

    return $subscription->fresh();
}

/**
 * Le geste canonique : une remise en euros, depuis la fiche d'affiliation.
 */
it('grants a discount in euros from the affiliation screen', function (): void {
    $subscription = affiliationToDiscount();

    discountScreen()
        ->call('openDiscount', $subscription->id)
        ->set('discountMode', 'amount')
        ->set('discountValue', 25.0)
        ->set('discountReason', 'Accord parents séparés')
        ->call('confirmDiscount')
        ->assertHasNoErrors();

    expect($subscription->fresh()->amount_due)->toBe(100.0)
        ->and($subscription->fresh()->discounts)->toHaveCount(1);
})->group('subscriptions', 'discount');

/**
 * Le pourcentage est un clavier, pas une promesse.
 *
 * Le secrétaire tape 30 %, le système calcule 37,50 € sur une affiliation à
 * 125 €, et c'est ce montant qui est enregistré. Si le membre ajoute un
 * entraînement en janvier, on lui redemande — la remise ne suit pas le prix.
 * Mais la trace du geste doit survivre, donc le pourcentage entre dans le
 * motif.
 */
it('converts a percentage into a frozen amount and keeps the percentage in the reason', function (): void {
    $subscription = affiliationToDiscount();

    discountScreen()
        ->call('openDiscount', $subscription->id)
        ->set('discountMode', 'percent')
        ->set('discountValue', 30.0)
        ->set('discountReason', 'Remerciement buvette')
        ->call('confirmDiscount')
        ->assertHasNoErrors();

    $discount = $subscription->fresh()->discounts->first();

    expect($discount->amount)->toBe(37.5)
        ->and($discount->reason)->toContain('30')
        ->and($discount->reason)->toContain('Remerciement buvette')
        ->and($subscription->fresh()->amount_due)->toBe(87.5);
})->group('subscriptions', 'discount');

it('refuses the gesture to someone without the discount permission', function (): void {
    $subscription = affiliationToDiscount();

    // Il gère les inscriptions — il ouvre donc l'écran — mais offrir n'est pas
    // gérer : inscrire quelqu'un à un pack et lui faire cadeau de 100 € ne sont
    // pas le même pouvoir, et c'est toute la raison d'une permission à part.
    $secretary = User::factory()->create();
    $secretary->givePermissionTo(Permission::SubscriptionsManage->value);
    $secretary->givePermissionTo(Permission::SubscriptionsView->value);

    Livewire::actingAs($secretary)
        ->test(REGISTRATIONS_COMPONENT)
        ->call('openDiscount', $subscription->id)
        ->assertForbidden();

    expect($subscription->fresh()->discounts)->toHaveCount(0);
})->group('subscriptions', 'discount');

/**
 * La remise se lit sur l'écran, motif compris.
 *
 * C'est l'argument qui a fait choisir une table plutôt qu'une colonne qui
 * s'additionne : « pourquoi cette affiliation est-elle à 100 € ? » doit se
 * répondre ici, pas en fouillant le journal d'audit.
 *
 * Deux affiliations, pas une : une fixture à un seul enregistrement rend le
 * test complaisant sur le chargement anticipé.
 */
it('shows the granted discounts and their reasons on the affiliation list', function (): void {
    $first = affiliationToDiscount();
    $second = affiliationToDiscount();

    foreach ([$first, $second] as $subscription) {
        discountScreen()
            ->call('openDiscount', $subscription->id)
            ->set('discountMode', 'amount')
            ->set('discountValue', 25.0)
            ->set('discountReason', 'Accord parents séparés')
            ->call('confirmDiscount');
    }

    discountScreen()
        ->call('review', $first->id)
        ->assertSee('Accord parents séparés')
        ->assertOk();
})->group('subscriptions', 'discount');
