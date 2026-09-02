<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Notifications\RefundRequestedNotification;
use App\Domains\ClubAdmin\Payment\Notifications\WeeklyRefundReminderNotification;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\TournamentDebtReminderNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentPaymentReminderNotification;
use App\Domains\Subscriptions\Notifications\SubscriptionRefundRequestedNotification;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;

function ibanNotificationLines(MailMessage $mail): string
{
    return implode("\n", [...$mail->introLines, ...$mail->outroLines]);
}

function ibanNotificationTestPayment(User $user): Payment
{
    $subscription = Subscription::factory()->create(['user_id' => $user->id]);

    return $subscription->payments()->create([
        'reference' => 'REF/2026/00099',
        'amount_due' => 1000,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);
}

it('shows the member IBAN grouped by 4 in the club-admin refund notification', function (): void {
    $member = User::factory()->create(['iban' => 'BE68539007547034']);
    $payment = ibanNotificationTestPayment($member);
    $tournament = Tournament::factory()->create();

    $mail = new RefundRequestedNotification($payment, $member, $tournament)->toMail($member);

    expect(ibanNotificationLines($mail))->toContain('BE68 5390 0754 7034');
});

it('shows the member IBAN grouped by 4 in the subscription refund notification', function (): void {
    $member = User::factory()->create(['iban' => 'BE68539007547034']);
    $subscription = Subscription::factory()->create(['user_id' => $member->id]);
    $payment = ibanNotificationTestPayment($member);

    $mail = new SubscriptionRefundRequestedNotification($payment, $subscription)->toMail($member);

    expect(ibanNotificationLines($mail))->toContain('BE68 5390 0754 7034');
});

it('shows the member IBAN grouped by 4 in the weekly refund reminder', function (): void {
    $user = User::factory()->create(['iban' => 'BE68539007547034']);
    $payment = ibanNotificationTestPayment($user);

    $mail = new WeeklyRefundReminderNotification(new Collection([$payment]))->toMail($user);

    expect(ibanNotificationLines($mail))->toContain('BE68 5390 0754 7034');
});

it('shows the club IBAN grouped by 4 in the tournament debt reminder', function (): void {
    Club::factory()->ownClub()->create(['bank_account' => 'BE68539007547034']);
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create();
    $payment = ibanNotificationTestPayment($user);

    $mail = new TournamentDebtReminderNotification($tournament, $payment)->toMail($user);

    expect(ibanNotificationLines($mail))->toContain('BE68 5390 0754 7034');
});

it('shows the club IBAN grouped by 4 in the tournament payment reminder', function (): void {
    Club::factory()->ownClub()->create(['bank_account' => 'BE68539007547034']);
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create();
    $payment = ibanNotificationTestPayment($user);

    $mail = new TournamentPaymentReminderNotification($tournament, $payment, Carbon::now()->addDay())->toMail($user);

    expect(ibanNotificationLines($mail))->toContain('BE68 5390 0754 7034');
});
