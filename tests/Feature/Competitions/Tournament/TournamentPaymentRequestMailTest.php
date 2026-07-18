<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Mail\TournamentPaymentRequestMail;
use Carbon\Carbon;

it('shows the club IBAN grouped by 4 for readability', function (): void {
    Club::factory()->ownClub()->create();

    $user = User::factory()->create();
    $tournament = Tournament::factory()->create();
    $subscription = Subscription::factory()->create(['user_id' => $user->id]);
    $payment = $subscription->payments()->create([
        'reference' => 'REF/2026/00777',
        'amount_due' => 1000,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $mailable = new TournamentPaymentRequestMail($tournament, $payment, Carbon::now()->addDay());

    $mailable->assertSeeInHtml('BE23 7323 3320 8791');
});

it('states the real deadline instead of a false 72h window (I6)', function (): void {
    Club::factory()->ownClub()->create();

    $user = User::factory()->create();
    $tournament = Tournament::factory()->create();
    $subscription = Subscription::factory()->create(['user_id' => $user->id]);
    $payment = $subscription->payments()->create([
        'reference' => 'REF/2026/00778',
        'amount_due' => 1000,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $mailable = new TournamentPaymentRequestMail($tournament, $payment, Carbon::create(2026, 9, 15));

    // The deadline is the date shown above, never a fixed 72h countdown.
    $mailable->assertSeeInHtml('15/09/2026');
    $mailable->assertDontSeeInHtml('72h');
});
