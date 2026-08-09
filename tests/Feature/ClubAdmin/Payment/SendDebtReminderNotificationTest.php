<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\TournamentDebtReminderNotification;
use App\Jobs\SendDebtReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('sends a debt reminder notification to the user when payment is pending', function (): void {
    Notification::fake();
    Event::fake();

    $user = User::factory()->create();
    $tournament = Tournament::factory()->create();

    $subscription = Subscription::factory()->create(['user_id' => $user->id]);
    $payment = $subscription->payments()->create([
        'reference' => 'REF/2026/00001',
        'amount_due' => 1000,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    new SendDebtReminderNotification($payment->id, $user->id, $tournament->id)->handle();

    Notification::assertSentTo($user, TournamentDebtReminderNotification::class);
});

it('does nothing when the payment status is not pending', function (): void {
    Notification::fake();
    Event::fake();

    $user = User::factory()->create();
    $tournament = Tournament::factory()->create();

    $subscription = Subscription::factory()->create(['user_id' => $user->id]);
    $payment = $subscription->payments()->create([
        'reference' => 'REF/2026/00002',
        'amount_due' => 1000,
        'amount_paid' => 1000,
        'status' => 'paid',
    ]);

    new SendDebtReminderNotification($payment->id, $user->id, $tournament->id)->handle();

    Notification::assertNothingSent();
});

it('does nothing when the payment does not exist', function (): void {
    Notification::fake();
    Event::fake();

    $user = User::factory()->create();
    $tournament = Tournament::factory()->create();

    new SendDebtReminderNotification(99999, $user->id, $tournament->id)->handle();

    Notification::assertNothingSent();
});

it('does nothing when the user does not exist', function (): void {
    Notification::fake();
    Event::fake();

    $tournament = Tournament::factory()->create();
    $subscription = Subscription::factory()->create();
    $payment = $subscription->payments()->create([
        'reference' => 'REF/2026/00003',
        'amount_due' => 1000,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    new SendDebtReminderNotification($payment->id, 99999, $tournament->id)->handle();

    Notification::assertNothingSent();
});
