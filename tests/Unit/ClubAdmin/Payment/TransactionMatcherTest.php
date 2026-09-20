<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Payment\Services\TransactionMatcher;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\MatchStrength;

function matcherPayment(User $member, float $amountDue = 150.0)
{
    $subscription = Subscription::factory()->create(['user_id' => $member->id]);

    return $subscription->payments()->create([
        'reference' => '011/0926/00405',
        'amount_due' => $amountDue,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);
}

it('recognises the member named in the communication', function (): void {
    $member = User::factory()->create(['first_name' => 'Félix', 'last_name' => 'Van de Borre']);
    $payment = matcherPayment($member);

    $transaction = Transaction::create([
        'date' => now(),
        'amount' => 90,
        'counterparty_name' => 'M ET MME JEAN DUPONT',
        'free_reference' => 'paiement cotisation pour Félix Van de Borre',
        'description' => 'VIREMENT EUROPEEN',
    ]);

    $match = (new TransactionMatcher)->score($payment, $transaction, amountIsUnique: false);

    expect($match->strength)->toBe(MatchStrength::TO_VERIFY);
});

it('reads the child surname inside a married couple counterparty', function (): void {
    $member = User::factory()->create(['first_name' => 'Léo', 'last_name' => 'Vermaut']);
    $payment = matcherPayment($member);

    // Le parent paie, son nom n'est pas celui de l'enfant, mais la moitié du
    // double nom l'est. Exiger le prénom raterait tout ce cas de figure.
    $transaction = Transaction::create([
        'date' => now(),
        'amount' => 90,
        'counterparty_name' => 'VERMAUT - PIRARD',
        'free_reference' => 'AFFILIATION 2024-25 MERCREDI',
        'description' => 'VIREMENT EUROPEEN',
    ]);

    $match = (new TransactionMatcher)->score($payment, $transaction, amountIsUnique: false);

    expect($match->strength)->toBe(MatchStrength::WEAK);
});

it('recognises the guardian who pays for a child with another surname', function (): void {
    $member = User::factory()->create(['first_name' => 'Quentin', 'last_name' => 'Vandevelde']);
    $guardian = Guardian::factory()->create(['first_name' => 'Michel', 'last_name' => 'Michotte']);
    $member->guardians()->attach($guardian->id);

    $payment = matcherPayment($member);

    $transaction = Transaction::create([
        'date' => now(),
        'amount' => 90,
        'counterparty_name' => 'M ET MME MICHEL MICHOTTE',
        'free_reference' => 'vandevelde Quentin affiliation 2025-2026 mercredi 15h',
        'description' => 'VIREMENT EUROPEEN',
    ]);

    // Nom fort du tuteur en contrepartie *et* nom fort du membre dans la
    // communication : deux gisements, c'est le cumul qui fait la force.
    $match = (new TransactionMatcher)->score($payment, $transaction, amountIsUnique: false);

    expect($match->strength)->toBe(MatchStrength::STRONG);
});

it('trusts a structured reference on its own, even when the amount differs', function (): void {
    $member = User::factory()->create(['first_name' => 'Sacha', 'last_name' => 'Van De Ponseele']);
    $payment = matcherPayment($member, amountDue: 150.0);

    // Une référence structurée est un identifiant émis par le club. Un montant
    // qui ne correspond pas est justement ce qui mérite l'oeil du trésorier,
    // pas un rejet silencieux.
    $transaction = Transaction::create([
        'date' => now(),
        'amount' => 90,
        'counterparty_name' => 'UN PARFAIT INCONNU',
        'structured_reference' => '+++011/0926/00405+++',
        'description' => 'VIREMENT EUROPEEN',
    ]);

    $match = (new TransactionMatcher)->score($payment, $transaction, amountIsUnique: false);

    expect($match->strength)->toBe(MatchStrength::STRONG);
});

it('matches the guardian IBAN the member never had on file', function (): void {
    $member = User::factory()->create(['first_name' => 'Quentin', 'last_name' => 'Vandevelde', 'iban' => null]);
    $guardian = Guardian::factory()->create([
        'first_name' => 'Michel',
        'last_name' => 'Michotte',
        'iban' => 'BE68 5390 0754 7034',
    ]);
    $member->guardians()->attach($guardian->id);

    $payment = matcherPayment($member);

    // L'IBAN arrive du relevé sans espaces : la comparaison doit survivre à la
    // mise en forme, des deux côtés.
    $transaction = Transaction::create([
        'date' => now(),
        'amount' => 90,
        'counterparty_name' => 'UN NOM QUI NE DIT RIEN',
        'counterparty_bank_account' => 'BE68539007547034',
        'description' => 'VIREMENT EUROPEEN',
    ]);

    $match = (new TransactionMatcher)->score($payment, $transaction, amountIsUnique: false);

    expect($match->strength)->toBe(MatchStrength::TO_VERIFY);
});

it('counts the amount only when no other candidate carries it', function (): void {
    $member = User::factory()->create(['first_name' => 'Sacha', 'last_name' => 'Van De Ponseele']);
    $payment = matcherPayment($member, amountDue: 150.0);

    $transaction = Transaction::create([
        'date' => now(),
        'amount' => 150,
        'counterparty_name' => 'UN NOM QUI NE DIT RIEN',
        'free_reference' => 'cotisation 2026-2027+1 entrainement (samedi)',
        'description' => 'VIREMENT EUROPEEN',
    ]);

    $matcher = new TransactionMatcher;

    // Cinq virements du même montant : le badge s'allumerait partout et
    // n'apprendrait rien. Seul, à ce montant, il vaut une piste.
    expect($matcher->score($payment, $transaction, amountIsUnique: false)->strength)
        ->toBe(MatchStrength::NONE)
        ->and($matcher->score($payment, $transaction, amountIsUnique: true)->strength)
        ->toBe(MatchStrength::WEAK);
});

it('ranks candidates and works out amount uniqueness on its own', function (): void {
    $member = User::factory()->create(['first_name' => 'Sacha', 'last_name' => 'Van De Ponseele']);
    $payment = matcherPayment($member, amountDue: 150.0);

    $anonymous = Transaction::create([
        'date' => now(), 'amount' => 150, 'counterparty_name' => 'M ET MME JEAN DUPONT',
        'free_reference' => 'cotisation 2026-2027', 'description' => 'VIREMENT',
    ]);
    $named = Transaction::create([
        'date' => now(), 'amount' => 150, 'counterparty_name' => 'VAN DE PONSEELE - MARTIN',
        'free_reference' => 'affiliation Sacha Van de Ponseele', 'description' => 'VIREMENT',
    ]);

    $ranked = (new TransactionMatcher)->rank($payment, collect([$anonymous, $named]));

    // Deux lignes à 150 € : le montant ne départage rien et ne doit rien dire.
    // La contrepartie ne porte que le patronyme, la communication porte le nom
    // complet : un seul gisement fort, donc « à vérifier » et non « forte ».
    expect($ranked->first()->id)->toBe($named->id)
        ->and($ranked->first()->match->strength)->toBe(MatchStrength::TO_VERIFY)
        ->and($ranked->last()->match->strength)->toBe(MatchStrength::NONE)
        ->and($ranked->last()->match->reasons)->toBe([]);
});
