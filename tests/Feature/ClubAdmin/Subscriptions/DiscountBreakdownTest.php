<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\GrantSubscriptionDiscountAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Ranking;
use App\Domains\Trainings\Models\TrainingPack;
use App\Mail\PaymentInvitationEmail;
use Livewire\Livewire;

/*
| Une communication remisée dit pourquoi elle ne vaut pas le prix normal.
|
| La remise vit sur l'affiliation, mais le membre la lit sur un paiement : la
| fenêtre de validation, le mail d'invitation et l'espace membre montrent le
| prix normal, la remise et son motif sous le montant à virer.
*/

beforeEach(function (): void {
    Club::factory()->ownClub()->create([
        'bic' => 'GEBABEBB',
        'bank_account' => 'BE68539007547034',
    ]);
    Club::forgetOwnClub();
});

/** Une affiliation confirmée et sa facture de cotisation, en attente ou payée. */
function invoicedAffiliation(string $paymentStatus = 'pending', ?User $member = null): Subscription
{
    $subscription = Subscription::factory()->create([
        'user_id' => ($member ?? User::factory()->create())->id,
        'status' => 'confirmed',
        'is_competitive' => true,
    ]);

    (new CalculatePriceAction)($subscription);

    $subscription->payments()->create([
        'reference' => '800/0000/' . fake()->unique()->numberBetween(10000, 99999),
        'amount_due' => 125,
        'amount_paid' => $paymentStatus === 'paid' ? 125 : 0,
        'status' => $paymentStatus,
    ]);

    return $subscription->fresh();
}

it('ties a discount to the communication it reduced', function (): void {
    $subscription = invoicedAffiliation();

    $granted = (new GrantSubscriptionDiscountAction)($subscription, 25.0, 'Remerciement buvette');

    $payment = $subscription->payments()->first();

    expect($granted->discount->payment_id)->toBe($payment->id)
        ->and($payment->amount_due)->toBe(100.0)
        ->and($payment->amountBeforeDiscounts())->toBe(125.0);
})->group('subscriptions', 'discount');

it('ties no communication when the discount is owed back rather than reduced', function (): void {
    $subscription = invoicedAffiliation('paid');

    $granted = (new GrantSubscriptionDiscountAction)($subscription, 25.0, 'Remerciement buvette');

    expect($granted->discount->payment_id)->toBeNull()
        ->and($granted->refundable)->toBe(25.0);
})->group('subscriptions', 'discount');

it('shows the normal price and the reason in the pack approval payment window', function (): void {
    $subscription = invoicedAffiliation();

    $pack = TrainingPack::factory()->create(['price' => 100, 'allow_discount' => false]);
    $subscription->trainingPacks()->attach($pack->id, ['status' => 'pending']);

    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-admin.users.registrations')
        ->call('reviewTrainingRequest', $subscription->id)
        ->set('approvedPackIds', [$pack->id])
        ->set('inlineDiscountMode', 'percent')
        ->set('inlineDiscountValue', 10.0)
        ->set('inlineDiscountReason', 'Révision des comptes')
        ->call('approveTrainingRequest')
        ->assertSet('paymentData.amount_due', 90.0)
        ->assertSet('paymentData.amount_before_discounts', 100.0)
        ->assertSee(__('Normal price'))
        ->assertSee('Révision des comptes');
})->group('subscriptions', 'discount');

it('ties the discount granted before the invoice to the invoice of a new affiliation', function (): void {
    $member = User::factory()->create(['licence' => '918274', 'ranking' => Ranking::NC]);

    $subscription = Subscription::factory()->pending()->create([
        'user_id' => $member->id,
        'is_competitive' => true,
    ]);

    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-admin.users.registrations')
        ->call('review', $subscription->id)
        ->set('inlineDiscountMode', 'amount')
        ->set('inlineDiscountValue', 25.0)
        ->set('inlineDiscountReason', 'Bénévolat au tournoi')
        ->call('approve')
        ->assertSet('paymentData.amount_due', 100.0)
        ->assertSet('paymentData.amount_before_discounts', 125.0)
        ->assertSee('Bénévolat au tournoi');

    $payment = $subscription->payments()->first();

    expect($subscription->discounts()->first()->payment_id)->toBe($payment->id);
})->group('subscriptions', 'discount');

it('explains the discount in the payment invitation email', function (): void {
    $subscription = invoicedAffiliation();
    (new GrantSubscriptionDiscountAction)($subscription, 25.0, 'Remerciement buvette');

    $html = new PaymentInvitationEmail($subscription->payments()->first())->render();

    expect($html)->toContain('100,00 €')
        ->toContain('Prix normal')
        ->toContain('125,00 €')
        ->toContain('Remerciement buvette');
})->group('subscriptions', 'discount');

it('says nothing about a discount in the email of an undiscounted payment', function (): void {
    $subscription = invoicedAffiliation();

    $html = new PaymentInvitationEmail($subscription->payments()->first())->render();

    expect($html)->not->toContain('Prix normal');
})->group('subscriptions', 'discount');

it('shows the normal price and the reason to the member', function (): void {
    $member = User::factory()->create();

    // Deux lignes remisées : une seule laisserait passer un chargement paresseux.
    foreach ([0, 1] as $ignored) {
        $subscription = invoicedAffiliation(member: $member);
        (new GrantSubscriptionDiscountAction)($subscription, 25.0, 'Remerciement buvette');
    }

    $payment = Payment::query()->whereHasMorph('payable', [Subscription::class], fn ($q) => $q->where('user_id', $member->id))->first();

    Livewire::actingAs($member)
        ->test('pages::club-admin.users.user-space.payments', ['user' => $member])
        ->assertSee('125,00')
        ->call('openPaymentModal', $payment->id)
        ->assertSee(__('Normal price'))
        ->assertSee('Remerciement buvette');
})->group('subscriptions', 'discount');

it('shows the normal price and the reason on the members season screen', function (): void {
    $season = makeActiveSeason();
    $member = User::factory()->create();

    $subscription = invoicedAffiliation(member: $member);
    $subscription->update(['season_id' => $season->id]);
    (new GrantSubscriptionDiscountAction)($subscription, 25.0, 'Remerciement buvette');

    Livewire::actingAs($member)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $member])
        ->assertSee('100.00')
        ->assertSee(__('Normal price'))
        ->assertSee('Remerciement buvette')
        ->call('openPaymentModal', $member->id, $subscription->payments()->first()->id)
        ->assertSet('paymentDetails.amount_before_discounts', 125.0)
        ->assertSet('paymentDetails.discounts.0.reason', 'Remerciement buvette');
})->group('subscriptions', 'discount');

it('keeps explaining the discount once the affiliation is paid', function (): void {
    $season = makeActiveSeason();
    $member = User::factory()->create();

    $subscription = invoicedAffiliation(member: $member);
    $subscription->update(['season_id' => $season->id]);
    (new GrantSubscriptionDiscountAction)($subscription, 25.0, 'Remerciement buvette');

    $subscription->payments()->first()->update(['status' => 'paid', 'amount_paid' => 100]);
    $subscription->update(['status' => 'paid']);

    Livewire::actingAs($member)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $member])
        ->assertSee(__('Affiliation paid — season confirmed!'))
        ->assertSee(__('Normal price'))
        ->assertSee('125,00')
        ->assertSee('Remerciement buvette');
})->group('subscriptions', 'discount');
