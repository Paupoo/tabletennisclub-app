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
use Livewire\Livewire;

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

/** Ce qu'un humain lit d'une page ou d'un mail : sans balises, sans CSS injecté, espaces resserrés. */
function readableText(string $html): string
{
    return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))));
}

function invitationText(Payment $payment): string
{
    return readableText(new PaymentInvitationEmail($payment)->render());
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

it('closes the discount breakdown with what came in, under the balance of the members QR window', function (): void {
    $member = User::factory()->create();
    $payment = partlyPaidAffiliation(60.0, $member);
    (new GrantSubscriptionDiscountAction)($payment->payable, 25.0, 'Remerciement buvette');

    $html = Livewire::actingAs($member)
        ->test('pages::club-admin.users.user-space.payments', ['user' => $member])
        ->call('openPaymentModal', $payment->id)
        ->html();

    expect(readableText($html))->toContain(implode(' ', [
        __('Amount'), '40,00 €',
        __('Normal price'), '125,00 €',
        'Remerciement buvette', '− 25,00 €',
        __('Already received'), '− 60,00 €',
        __('Reference'),
    ]));
})->group('payments', 'discount');

it('asks the member for the balance in the payment window of their season screen', function (): void {
    $season = makeActiveSeason();
    $member = User::factory()->create();
    $payment = partlyPaidAffiliation(60.0, $member);
    $payment->payable->update(['season_id' => $season->id]);
    (new GrantSubscriptionDiscountAction)($payment->payable, 25.0, 'Remerciement buvette');

    $html = Livewire::actingAs($member)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $member])
        ->call('openPaymentModal', $member->id, $payment->id)
        ->html();

    expect(readableText($html))->toContain(implode(' ', [
        __('Amount'), '40,00 €',
        __('Normal price'), '125,00 €',
        'Remerciement buvette', '− 25,00 €',
        __('Already received'), '− 60,00 €',
    ]));
})->group('payments', 'discount');

it('settles a partly paid line once a discount brings it down to what came in', function (): void {
    $payment = partlyPaidAffiliation(60.0);

    (new GrantSubscriptionDiscountAction)($payment->payable, 65.0, 'Remerciement buvette');

    expect($payment->fresh()->status)->toBe('paid')
        ->and($payment->fresh()->balance())->toBe(0.0)
        ->and($payment->payable->fresh()->getStatus())->toBe('paid');
})->group('payments', 'discount');
