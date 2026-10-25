<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\TournamentRegistrationConfirmedNotification;
use App\Mail\TournamentRegistrationMail;
use Carbon\Carbon;
use Illuminate\Mail\Mailables\Attachment;

/*
| The one message a registration sends: what the tournament is, and — when it
| costs something — how to pay for it. It used to be two, the first saying
| "confirmed" and the second "not final until paid, or cancelled".
*/

function registrationPayment(User $user, string $reference = 'REF/2026/00777'): Payment
{
    return Subscription::factory()->create(['user_id' => $user->id])->payments()->create([
        'reference' => $reference,
        'amount_due' => 15,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);
}

/** @return list<string> */
function attachmentNames(TournamentRegistrationMail $mailable): array
{
    return collect($mailable->attachments())->map(fn (Attachment $attachment): ?string => $attachment->as)->all();
}

it('shows the club IBAN grouped by 4 for readability', function (): void {
    Club::factory()->ownClub()->create();
    $user = User::factory()->create();

    $mailable = new TournamentRegistrationMail(Tournament::factory()->create(), $user, registrationPayment($user), Carbon::now()->addDay());

    $mailable->assertSeeInHtml('BE23 7323 3320 8791');
});

it('states the real deadline instead of a false 72h window (I6)', function (): void {
    Club::factory()->ownClub()->create();
    $user = User::factory()->create();

    $mailable = new TournamentRegistrationMail(Tournament::factory()->create(), $user, registrationPayment($user, 'REF/2026/00778'), Carbon::create(2026, 9, 15));

    // The deadline is the date shown above, never a fixed 72h countdown.
    $mailable->assertSeeInHtml('15/09/2026');
    $mailable->assertDontSeeInHtml('72h');
});

it('asks for the payment in the registration message itself, with the tournament and its calendar entry', function (): void {
    Club::factory()->ownClub()->create();
    $user = User::factory()->create(['first_name' => 'Aurélien']);
    $tournament = Tournament::factory()->create(['name' => 'Tournoi de doubles', 'start_date' => '2026-11-29', 'start_time' => '13:00:00']);

    $mailable = new TournamentRegistrationMail($tournament, $user, registrationPayment($user, '021/1026/00479'), Carbon::create(2026, 11, 25));

    $mailable->assertHasSubject(__(':tournament — Registration recorded', ['tournament' => 'Tournoi de doubles']));
    $mailable->assertSeeInHtml('Aurélien');
    $mailable->assertSeeInHtml('29/11/2026');
    $mailable->assertSeeInHtml('021/1026/00479');
    $mailable->assertSeeInHtml('15,00');
    $mailable->assertSeeInHtml('25/11/2026');
    expect(attachmentNames($mailable))->toEqualCanonicalizing(['tournament.ics', 'qr-paiement.png']);
});

it('promises nothing the application does not do, and calls nothing a reminder', function (): void {
    Club::factory()->ownClub()->create();
    $user = User::factory()->create();

    $mailable = new TournamentRegistrationMail(Tournament::factory()->create(), $user, registrationPayment($user), Carbon::now()->addDays(5));

    // Nothing cancels an unpaid registration: the message may not threaten it.
    $mailable->assertDontSeeInHtml('annul');
    $mailable->assertDontSeeInHtml('rappel');
    $mailable->assertDontSeeInHtml('confirmée');
});

it('leaves the bank out of a free tournament, and needs no club row for it', function (): void {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['price' => 0, 'start_date' => '2026-11-29']);

    $mailable = new TournamentRegistrationMail($tournament, $user);

    $mailable->assertDontSeeInHtml('IBAN');
    expect(attachmentNames($mailable))->toBe(['tournament.ics']);
});

it('says a paid tournament entered on the day is paid at the desk', function (): void {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['price' => 12, 'start_date' => '2026-11-29']);

    $mailable = new TournamentRegistrationMail($tournament, $user);

    $mailable->assertSeeInHtml(__('To pay on site: :amount €', ['amount' => '12,00']));
    $mailable->assertDontSeeInHtml('IBAN');
});

it('is the mail of the registration notification, unless the player is only waiting', function (): void {
    Club::factory()->ownClub()->create();
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create();
    $payment = registrationPayment($user);

    expect(new TournamentRegistrationConfirmedNotification($tournament, payment: $payment, deadline: now()->addDay())->toMail($user))
        ->toBeInstanceOf(TournamentRegistrationMail::class)
        ->and(new TournamentRegistrationConfirmedNotification($tournament, isWaitlisted: true, waitlistPosition: 2)->toMail($user))
        ->not->toBeInstanceOf(TournamentRegistrationMail::class);
});
