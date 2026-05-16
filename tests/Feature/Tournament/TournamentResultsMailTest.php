<?php

declare(strict_types=1);

use App\Mail\TournamentResultsMail;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

// ── Envelope ──────────────────────────────────────────────────────────────────

describe('TournamentResultsMail — envelope', function () {

    it('uses emailSubject as the envelope subject', function () {
        $tournament = Tournament::factory()->create();
        $recipient = User::factory()->create();

        $mail = new TournamentResultsMail(
            tournament: $tournament,
            recipient: $recipient,
            emailSubject: 'Résultats — Open de Printemps',
            emailBody: 'Merci pour votre participation !',
            rankings: collect(),
        );

        $envelope = $mail->envelope();

        expect($envelope->subject)->toBe('Résultats — Open de Printemps');
    })->group('mail', 'tournament');

    it('uses the configured from address', function () {
        $tournament = Tournament::factory()->create();
        $recipient = User::factory()->create();

        $mail = new TournamentResultsMail(
            tournament: $tournament,
            recipient: $recipient,
            emailSubject: 'Test subject',
            emailBody: 'Test body',
            rankings: collect(),
        );

        $envelope = $mail->envelope();

        expect($envelope->from->address)->toBe(config('mail.from.address'));
    })->group('mail', 'tournament');

})->group('mail');

// ── Content ───────────────────────────────────────────────────────────────────

describe('TournamentResultsMail — content', function () {

    it('uses the tournament-results markdown view', function () {
        $tournament = Tournament::factory()->create();
        $recipient = User::factory()->create();

        $mail = new TournamentResultsMail(
            tournament: $tournament,
            recipient: $recipient,
            emailSubject: 'Test',
            emailBody: 'Body text',
            rankings: collect(),
        );

        $content = $mail->content();

        expect($content->markdown)->toBe('mail.tournament-results');
    })->group('mail', 'tournament');

    it('exposes emailBody as a public property accessible to the view', function () {
        $tournament = Tournament::factory()->create();
        $recipient = User::factory()->create();

        $mail = new TournamentResultsMail(
            tournament: $tournament,
            recipient: $recipient,
            emailSubject: 'Test',
            emailBody: 'Chers participants, merci !',
            rankings: collect(),
        );

        expect($mail->emailBody)->toBe('Chers participants, merci !');
    })->group('mail', 'tournament');

    it('exposes rankings as a public property accessible to the view', function () {
        $tournament = Tournament::factory()->create();
        $recipient = User::factory()->create();
        $p1 = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);

        $rankings = collect([
            ['user' => $p1, 'rank' => 1, 'result' => 'Champion'],
        ]);

        $mail = new TournamentResultsMail(
            tournament: $tournament,
            recipient: $recipient,
            emailSubject: 'Test',
            emailBody: 'Body',
            rankings: $rankings,
        );

        expect($mail->rankings)->toHaveCount(1)
            ->and($mail->rankings->first()['rank'])->toBe(1)
            ->and($mail->rankings->first()['result'])->toBe('Champion');
    })->group('mail', 'tournament');

})->group('mail');

// ── Queue dispatch ────────────────────────────────────────────────────────────

describe('TournamentResultsMail — queueing', function () {

    it('can be queued via Mail::to()->queue()', function () {
        Mail::fake();

        $tournament = Tournament::factory()->create(['name' => 'Open Printemps']);
        $recipient = User::factory()->create();

        Mail::to($recipient->email)->queue(new TournamentResultsMail(
            tournament: $tournament,
            recipient: $recipient,
            emailSubject: 'Résultats — Open Printemps',
            emailBody: 'Merci !',
            rankings: collect(),
        ));

        Mail::assertQueued(TournamentResultsMail::class, function (TournamentResultsMail $m) use ($recipient) {
            return $m->recipient->is($recipient)
                && $m->emailSubject === 'Résultats — Open Printemps';
        });
    })->group('mail', 'tournament', 'queue');

    it('queues one email per participant', function () {
        Mail::fake();

        $tournament = Tournament::factory()->create();
        $participants = User::factory(3)->create();

        foreach ($participants as $user) {
            Mail::to($user->email)->queue(new TournamentResultsMail(
                tournament: $tournament,
                recipient: $user,
                emailSubject: 'Résultats',
                emailBody: 'Merci',
                rankings: collect(),
            ));
        }

        Mail::assertQueuedCount(3);
    })->group('mail', 'tournament', 'queue');

    it('implements ShouldQueue', function () {
        $mail = new TournamentResultsMail(
            tournament: Tournament::factory()->create(),
            recipient: User::factory()->create(),
            emailSubject: 'Test',
            emailBody: 'Test',
            rankings: collect(),
        );

        expect($mail)->toBeInstanceOf(ShouldQueue::class);
    })->group('mail', 'tournament');

})->group('mail');
