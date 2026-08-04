<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Mail\PaymentInvitationEmail;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    Mail::fake();
    $this->treasurer = $this->createFakeAdmin();
});

function paymentWithStatus(string $status): object
{
    $subscription = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 125]);

    return $subscription->payments()->create([
        'reference' => '100/2505/0010' . random_int(1, 9),
        'amount_due' => 125,
        'amount_paid' => $status === 'paid' ? 125 : 0,
        'status' => $status,
    ]);
}

/*
 * sendReminder checks who may click, never whether the payment is still owed.
 * Only the tab filter hides the button, which is a rendering detail: a stale
 * Livewire request or a double click during a tab change is enough to dun a
 * member who has already settled.
 */
test('a settled payment cannot be chased', function (string $status): void {
    $payment = paymentWithStatus($status);

    Livewire::actingAs($this->treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->call('sendReminder', $payment->id);

    // The mailable is ShouldQueue, so assertSent never sees it — assert on the
    // outgoing queue instead, or the check passes without proving anything.
    Mail::assertNothingOutgoing();

    expect($payment->fresh()->invitation_counter)->toBe(0);
})->with(['paid', 'to_refund']);

test('a pending payment is still chased', function (): void {
    $payment = paymentWithStatus('pending');

    Livewire::actingAs($this->treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->call('sendReminder', $payment->id);

    Mail::assertQueued(PaymentInvitationEmail::class);

    expect($payment->fresh()->invitation_counter)->toBe(1);
});
