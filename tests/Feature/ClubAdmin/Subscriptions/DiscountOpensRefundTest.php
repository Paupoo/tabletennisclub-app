<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\GrantSubscriptionDiscountAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Notification;

/*
| Une remise sur une affiliation déjà payée ouvre le remboursement qu'elle crée.
|
| Elle se contentait de le signaler au secrétaire, qui devait passer le relais
| à la trésorerie ; et la trésorerie n'avait rien à montrer, l'excédent ne
| vivant sur aucune ligne. Le remboursement part du geste lui-même, vers le
| compte qui a payé — l'IBAN du membre seulement quand l'argent n'est venu
| d'aucun virement.
*/

/** Une cotisation de 125 € entièrement réglée par `$settle`. */
function paidAffiliationFor(User $member, callable $settle): Subscription
{
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'is_competitive' => true,
    ]);

    (new CalculatePriceAction)($subscription);

    $claim = $subscription->payments()->create([
        'reference' => '800/0000/' . fake()->unique()->numberBetween(10000, 99999),
        'amount_due' => 125,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $settle($claim);

    return $subscription->fresh();
}

function openedRefund(Subscription $subscription): ?Payment
{
    return $subscription->payments()->where('payment_method', 'refund')->first();
}

it('opens the refund towards the account whose transfer paid the affiliation', function (): void {
    Notification::fake();
    $member = User::factory()->create(['iban' => 'BE68539007547034']);

    $subscription = paidAffiliationFor($member, function (Payment $claim): void {
        $transfer = Transaction::create([
            'date' => now()->toDateString(),
            'description' => 'COTISATION',
            'amount' => 125.0,
            'counterparty_name' => 'Parent du membre',
            'counterparty_bank_account' => 'BE62510007547061',
        ]);

        (new AllocateTransactionAction)($transfer, [$claim->id => 125.0]);
    });

    (new GrantSubscriptionDiscountAction)($subscription, 25.0, 'Remerciement buvette');

    $refund = openedRefund($subscription);

    expect($refund)->not->toBeNull()
        ->and($refund->status)->toBe('to_refund')
        ->and($refund->amount_due)->toBe(25.0)
        ->and($refund->refund_iban)->toBe('BE62510007547061');
})->group('subscriptions', 'discount');

it('opens the refund towards the member when the money came from no transfer', function (): void {
    Notification::fake();
    $member = User::factory()->create(['iban' => 'BE68539007547034']);

    $subscription = paidAffiliationFor($member, fn (Payment $claim) => (new AllocateTransactionAction)->credit($claim, 125.0, 'cash'));

    (new GrantSubscriptionDiscountAction)($subscription, 25.0, 'Remerciement buvette');

    expect(openedRefund($subscription)?->refund_iban)->toBe('BE68539007547034');
})->group('subscriptions', 'discount');

it('opens no refund when the discount only lowers what is still owed', function (): void {
    $subscription = paidAffiliationFor(User::factory()->create(), fn (Payment $claim) => null);

    (new GrantSubscriptionDiscountAction)($subscription, 25.0, 'Remerciement buvette');

    expect(openedRefund($subscription))->toBeNull();
})->group('subscriptions', 'discount');
