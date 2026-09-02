<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Jobs\SendPaymentReminderJob;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    Mail::fake();
    $this->treasurer = $this->createFakeAdmin();
});

/*
 * A treasurer does not think in "two reminders", they think "it has been three
 * weeks, I chase again". The count was already stored; the date was not, so the
 * screen could never answer the question that actually triggers the action.
 */
test('a reminder records when it was sent', function (): void {
    $subscription = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 125]);
    $payment = $subscription->payments()->create([
        'reference' => '100/2505/00111',
        'amount_due' => 125,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    expect($payment->last_reminded_at)->toBeNull();

    Livewire::actingAs($this->treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->call('sendReminder', $payment->id);

    $payment->refresh();

    expect($payment->last_reminded_at)->not->toBeNull()
        ->and($payment->last_reminded_at->isToday())->toBeTrue()
        ->and($payment->invitation_counter)->toBe(1);
});

test('a bulk reminder records the date too', function (): void {
    $subscription = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 125]);
    $payment = $subscription->payments()->create([
        'reference' => '100/2505/00112',
        'amount_due' => 125,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    // The bulk path goes through a job rather than the component, and it already
    // refused settled payments — the single-click path was the one missing that.
    new SendPaymentReminderJob($payment->id)->handle();

    expect($payment->fresh()->last_reminded_at)->not->toBeNull();
});
