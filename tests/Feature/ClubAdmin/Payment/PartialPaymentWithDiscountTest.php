<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\GrantSubscriptionDiscountAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Mail\PaymentInvitationEmail;

/*
| Une communication à la fois remisée et partiellement payée.
|
| Deux branches se sont croisées : la remise (#146) raconte le prix normal sous
| le montant dû, le paiement partiel raconte ce qui est déjà rentré. Le membre
| lit les deux au même endroit, et le montant qu'on lui réclame doit être le
| bout du décompte, pas son début.
*/

beforeEach(function (): void {
    Club::factory()->ownClub()->create([
        'bic' => 'GEBABEBB',
        'bank_account' => 'BE68539007547034',
    ]);
    Club::forgetOwnClub();
});

/** Une cotisation de 125 € en attente, sur laquelle `$received` € sont déjà rentrés en espèces. */
function partlyPaidAffiliation(float $received, ?User $member = null): Payment
{
    $subscription = Subscription::factory()->create([
        'user_id' => ($member ?? User::factory()->create())->id,
        'status' => 'confirmed',
        'is_competitive' => true,
    ]);

    (new CalculatePriceAction)($subscription);

    $payment = $subscription->payments()->create([
        'reference' => '800/0000/' . fake()->unique()->numberBetween(10000, 99999),
        'amount_due' => 125,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    if ($received > 0.0) {
        (new AllocateTransactionAction)->credit($payment, $received, 'cash');
    }

    return $payment->fresh();
}

/** Le texte du mail tel que le membre le lit : sans balises ni CSS injecté. */
function invitationText(Payment $payment): string
{
    $html = new PaymentInvitationEmail($payment)->render();

    return (string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html)));
}

it('itemises the normal price, the discount and what came in down to the balance in the email', function (): void {
    $payment = partlyPaidAffiliation(60.0);
    (new GrantSubscriptionDiscountAction)($payment->payable, 25.0, 'Remerciement buvette');

    expect(invitationText($payment->fresh()))->toContain('Montant à régler : 40,00 €')
        ->toContain('Prix normal : 125,00 €')
        ->toContain('Remise : − 25,00 €')
        ->toContain('Déjà reçu : − 60,00 €');
})->group('payments', 'discount');

it('calls the starting figure the initial amount when nothing was discounted', function (): void {
    $payment = partlyPaidAffiliation(60.0);

    expect(invitationText($payment))->toContain('Montant à régler : 65,00 €')
        ->toContain('Montant initial : 125,00 €')
        ->toContain('Déjà reçu : − 60,00 €')
        ->not->toContain('Prix normal');
})->group('payments', 'discount');
