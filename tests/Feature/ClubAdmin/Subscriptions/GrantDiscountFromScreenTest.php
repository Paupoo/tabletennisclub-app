<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Ranking;
use App\Domains\Shared\Enums\Role;
use App\Domains\Trainings\Models\TrainingPack;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const DISCOUNT_SCREEN_COMPONENT = 'pages::club-admin.users.registrations';

function discountScreen(): Testable
{
    return Livewire::actingAs(User::factory()->isAdmin()->create())->test(DISCOUNT_SCREEN_COMPONENT);
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
        ->test(DISCOUNT_SCREEN_COMPONENT)
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

/**
 * Le secrétaire — la délégation MEMBERS — accorde les remises.
 *
 * La permission est à part pour que le comité puisse la déplacer sans toucher
 * au code, mais par défaut elle vit là où le geste se fait.
 */
it('lets the members delegation grant a discount', function (): void {
    $subscription = affiliationToDiscount();

    $secretary = User::factory()->create();
    $secretary->assignRole(Role::MEMBERS->value);

    Livewire::actingAs($secretary)
        ->test(DISCOUNT_SCREEN_COMPONENT)
        ->call('openDiscount', $subscription->id)
        ->set('discountMode', 'amount')
        ->set('discountValue', 25.0)
        ->set('discountReason', 'Accord parents séparés')
        ->call('confirmDiscount')
        ->assertHasNoErrors();

    expect($subscription->fresh()->amount_due)->toBe(100.0);
})->group('subscriptions', 'discount');

/**
 * Le raccourci au fil de la validation d'une demande de pack.
 *
 * Le complément est facturé, puis la remise le rabote : le membre reçoit une
 * communication au montant remisé, pas une relance pour un prix qu'on vient de
 * lui faire baisser.
 */
it('discounts a mid-season pack approval without billing the full complement', function (): void {
    // Le complément facturé construit un QR, qui a besoin du club et de son IBAN.
    Club::factory()->ownClub()->create([
        'bic' => 'GEBABEBB',
        'bank_account' => 'BE68539007547034',
    ]);
    Club::forgetOwnClub();

    $subscription = affiliationToDiscount();

    $pack = TrainingPack::factory()->create(['price' => 100, 'allow_discount' => false]);
    $subscription->trainingPacks()->attach($pack->id, ['status' => 'pending']);

    // Une facture déjà émise : c'est elle qui rend le complément facturable.
    $subscription->payments()->create([
        'reference' => '600/0000/00001',
        'amount_due' => 125,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    discountScreen()
        ->call('reviewTrainingRequest', $subscription->id)
        ->set('approvedPackIds', [$pack->id])
        ->set('inlineDiscountMode', 'amount')
        ->set('inlineDiscountValue', 40.0)
        ->set('inlineDiscountReason', 'Une semaine sur deux')
        ->call('approveTrainingRequest')
        ->assertHasNoErrors();

    $subscription = $subscription->fresh();

    expect($subscription->discounts)->toHaveCount(1)
        ->and($subscription->discounts->first()->amount)->toBe(40.0)
        // 125 de cotisation + 100 de pack − 40 de remise
        ->and($subscription->amount_due)->toBe(185.0)
        // Ce qu'on réclame suit ce qu'on doit.
        ->and(round((float) $subscription->payments()->where('status', 'pending')->sum('amount_due') / 100, 2))
        ->toBe(185.0);
})->group('subscriptions', 'discount');

/**
 * Un pourcentage porte sur le prix qu'on décide, et la fenêtre le montre.
 *
 * Deux défauts vus en vrai : 10 % sur deux packs à 160 € avaient pris
 * 10 % des 285 € dus (cotisation comprise), et la fenêtre de paiement,
 * construite avant la remise, affichait encore le complément plein.
 */
it('takes a percentage of the pack being approved and shows the discounted complement', function (): void {
    Club::factory()->ownClub()->create([
        'bic' => 'GEBABEBB',
        'bank_account' => 'BE68539007547034',
    ]);
    Club::forgetOwnClub();

    $subscription = affiliationToDiscount();

    $pack = TrainingPack::factory()->create(['price' => 100, 'allow_discount' => false]);
    $subscription->trainingPacks()->attach($pack->id, ['status' => 'pending']);

    $subscription->payments()->create([
        'reference' => '600/0000/00002',
        'amount_due' => 125,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    discountScreen()
        ->call('reviewTrainingRequest', $subscription->id)
        ->set('approvedPackIds', [$pack->id])
        ->set('inlineDiscountMode', 'percent')
        ->set('inlineDiscountValue', 10.0)
        ->set('inlineDiscountReason', 'Révision des comptes')
        ->call('approveTrainingRequest')
        ->assertHasNoErrors()
        ->assertSet('paymentGenerated', true)
        ->assertSet('paymentData.amount_due', 90.0);

    $subscription = $subscription->fresh();

    // 10 % des 100 € du pack, pas des 225 € dus.
    expect($subscription->discounts->first()->amount)->toBe(10.0)
        ->and($subscription->amount_due)->toBe(215.0);
})->group('subscriptions', 'discount');

it('shows no payment window when the complement is discounted away', function (): void {
    Club::factory()->ownClub()->create([
        'bic' => 'GEBABEBB',
        'bank_account' => 'BE68539007547034',
    ]);
    Club::forgetOwnClub();

    $subscription = affiliationToDiscount();

    $pack = TrainingPack::factory()->create(['price' => 100, 'allow_discount' => false]);
    $subscription->trainingPacks()->attach($pack->id, ['status' => 'pending']);

    $subscription->payments()->create([
        'reference' => '600/0000/00003',
        'amount_due' => 125,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    discountScreen()
        ->call('reviewTrainingRequest', $subscription->id)
        ->set('approvedPackIds', [$pack->id])
        ->set('inlineDiscountMode', 'percent')
        ->set('inlineDiscountValue', 100.0)
        ->set('inlineDiscountReason', 'Offert')
        ->call('approveTrainingRequest')
        ->assertHasNoErrors()
        ->assertSet('paymentGenerated', false);

    expect($subscription->fresh()->amount_due)->toBe(125.0);
})->group('subscriptions', 'discount');

/**
 * Le raccourci au fil de la validation d'une nouvelle affiliation.
 *
 * Ici la remise doit passer **avant** la facture : le paiement naît du montant
 * dû, donc la communication que le membre reçoit doit déjà porter le prix
 * remisé — pas un plein tarif corrigé après coup.
 */
it('bills a newly approved affiliation at the discounted price', function (): void {
    Club::factory()->ownClub()->create([
        'bic' => 'GEBABEBB',
        'bank_account' => 'BE68539007547034',
    ]);
    Club::forgetOwnClub();

    $member = User::factory()->create(['licence' => '918273', 'ranking' => Ranking::NC]);

    $subscription = Subscription::factory()->pending()->create([
        'user_id' => $member->id,
        'is_competitive' => true,
    ]);

    discountScreen()
        ->call('review', $subscription->id)
        ->set('inlineDiscountMode', 'percent')
        ->set('inlineDiscountValue', 20.0)
        ->set('inlineDiscountReason', 'Bénévolat au tournoi')
        ->call('approve')
        ->assertHasNoErrors();

    $subscription = $subscription->fresh();
    $payment = $subscription->payments()->first();

    // 125 € de cotisation, 20 % offerts.
    expect($subscription->discounts)->toHaveCount(1)
        ->and($subscription->amount_due)->toBe(100.0)
        ->and($payment->amount_due)->toBe(100.0)
        ->and($subscription->discounts->first()->reason)->toContain('20');
})->group('subscriptions', 'discount');

/**
 * Le raccourci au fil de l'ajout manuel par le comité.
 *
 * Le seul des trois moments qui n'a pas d'écran de validation — la docstring
 * de AddMemberToTrainingPackAction l'assume : « le comité n'a pas à valider sa
 * propre décision ». Mais il facture un complément, donc la question du prix
 * s'y pose comme ailleurs.
 */
it('discounts a pack the committee adds by hand', function (): void {
    $member = User::factory()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'is_competitive' => true,
    ]);

    (new CalculatePriceAction)($subscription);

    // Une facture déjà émise : sans elle, rien ne serait réclamé en plus.
    $subscription->payments()->create([
        'reference' => '700/0000/00001',
        'amount_due' => 125,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $pack = TrainingPack::factory()->create([
        'price' => 100,
        'allow_discount' => false,
        'season_id' => $subscription->season_id,
    ]);

    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.trainings.index')
        ->set('selectedPackId', $pack->id)
        ->set('addMemberUserId', $member->id)
        ->set('inlineDiscountMode', 'amount')
        ->set('inlineDiscountValue', 30.0)
        ->set('inlineDiscountReason', 'Accord parents séparés')
        ->call('addMemberToPack')
        ->assertHasNoErrors();

    $subscription = $subscription->fresh();

    expect($subscription->discounts)->toHaveCount(1)
        // 125 + 100 − 30
        ->and($subscription->amount_due)->toBe(195.0)
        ->and(round((float) $subscription->payments()->where('status', 'pending')->sum('amount_due') / 100, 2))
        ->toBe(195.0);
})->group('subscriptions', 'discount');

it('takes a percentage of the pack the committee adds, not of the whole affiliation', function (): void {
    $member = User::factory()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'is_competitive' => true,
    ]);

    (new CalculatePriceAction)($subscription);

    $subscription->payments()->create([
        'reference' => '700/0000/00002',
        'amount_due' => 125,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $pack = TrainingPack::factory()->create([
        'price' => 100,
        'allow_discount' => false,
        'season_id' => $subscription->season_id,
    ]);

    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.trainings.index')
        ->set('selectedPackId', $pack->id)
        ->set('addMemberUserId', $member->id)
        ->set('inlineDiscountMode', 'percent')
        ->set('inlineDiscountValue', 10.0)
        ->set('inlineDiscountReason', 'Accord parents séparés')
        ->call('addMemberToPack')
        ->assertHasNoErrors();

    $subscription = $subscription->fresh();

    expect($subscription->discounts->first()->amount)->toBe(10.0)
        ->and($subscription->amount_due)->toBe(215.0);
})->group('subscriptions', 'discount');

/**
 * Le champ doit être **atteignable** dans la page rendue.
 *
 * Les tests précédents posaient `inlineDiscountValue` directement sur le
 * composant : ils prouvaient que la remise s'applique, jamais qu'un humain
 * puisse la saisir. Le repli avait été posé dans une branche du gabarit gardée
 * par `status !== 'pending'`, sous une condition `status === 'pending'` —
 * jamais vraie, donc invisible exactement là où il servait.
 */
it('shows the inline discount field while reviewing a pending affiliation', function (): void {
    $subscription = Subscription::factory()->pending()->create([
        'user_id' => User::factory()->create(['licence' => '445566'])->id,
        'is_competitive' => true,
    ]);

    discountScreen()
        ->call('review', $subscription->id)
        ->assertSeeHtml('wire:model="inlineDiscountReason"');
})->group('subscriptions', 'discount');

/**
 * Et sur une affiliation déjà confirmée, c'est le bouton du geste canonique
 * qui doit être là.
 */
it('shows the canonical discount button on a confirmed affiliation', function (): void {
    $subscription = affiliationToDiscount();

    discountScreen()
        ->call('review', $subscription->id)
        ->assertSeeHtml('openDiscount(' . $subscription->id . ')');
})->group('subscriptions', 'discount');

/**
 * Le même contrôle sur les deux autres emplacements : le champ doit être dans
 * la page, pas seulement dans le composant.
 */
it('shows the inline discount field while reviewing a mid-season pack request', function (): void {
    $subscription = affiliationToDiscount();

    $pack = TrainingPack::factory()->create([
        'price' => 90,
        'season_id' => $subscription->season_id,
    ]);
    $subscription->trainingPacks()->attach($pack->id, ['status' => 'pending']);

    discountScreen()
        ->call('reviewTrainingRequest', $subscription->id)
        ->assertSeeHtml('wire:model="inlineDiscountReason"');
})->group('subscriptions', 'discount');

it('shows the inline discount field when the committee adds a member to a pack', function (): void {
    $pack = TrainingPack::factory()->create(['price' => 90]);

    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.trainings.index')
        ->set('selectedPackId', $pack->id)
        ->set('addMemberModal', true)
        ->assertSeeHtml('wire:model="inlineDiscountReason"');
})->group('subscriptions', 'discount');
