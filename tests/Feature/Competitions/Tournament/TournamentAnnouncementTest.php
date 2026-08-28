<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\NewTournamentPublishedNotification;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\Events\Tournament\NewTournamentPublished;
use App\Jobs\SendTournamentAnnouncementJob;
use App\Listeners\Tournament\SendPublishedTournamentNotification;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

pest()->group('tournaments', 'notifications');

/*
 * Issue #81. The announcement had two faults that had to be fixed together: the
 * event never fired, and the listener looped over User::cursor() — every row in
 * the table — with no rate limit. Repairing the trigger on its own would have
 * turned a silent bug into an unfiltered mass mailing on the first tournament
 * opened, through a Gmail account.
 */

function announcedTournament(): Tournament
{
    return Tournament::factory()->create([
        'status' => TournamentStatusEnum::PUBLISHED,
        'name' => 'Tournoi des crêpes',
    ]);
}

describe('who hears about a new tournament', function (): void {
    it('tells the active members', function (): void {
        Bus::fake([SendTournamentAnnouncementJob::class]);

        $season = makeActiveSeason();
        $members = collect(range(1, 3))->map(fn (): User => activeMember($season));
        $tournament = announcedTournament();

        (new SendPublishedTournamentNotification)->handle(new NewTournamentPublished($tournament));

        Bus::assertDispatchedTimes(SendTournamentAnnouncementJob::class, 3);

        foreach ($members as $member) {
            Bus::assertDispatched(
                SendTournamentAnnouncementJob::class,
                fn (SendTournamentAnnouncementJob $job): bool => $job->userId === $member->id
                    && $job->tournamentId === $tournament->id,
            );
        }
    });

    /*
     * The whole reason this was not a one-line fix: User::cursor() would have
     * written to people who left the club years ago.
     */
    it('leaves out everybody who is not an active member', function (): void {
        Bus::fake([SendTournamentAnnouncementJob::class]);

        $season = makeActiveSeason();
        $member = activeMember($season);
        $stranger = User::factory()->create();          // never affiliated
        $formerMember = User::factory()->create();      // affiliated, but not this season

        (new SendPublishedTournamentNotification)->handle(
            new NewTournamentPublished(announcedTournament())
        );

        Bus::assertDispatchedTimes(SendTournamentAnnouncementJob::class, 1);
        Bus::assertDispatched(
            SendTournamentAnnouncementJob::class,
            fn (SendTournamentAnnouncementJob $job): bool => $job->userId === $member->id,
        );

        foreach ([$stranger, $formerMember] as $outsider) {
            Bus::assertNotDispatched(
                SendTournamentAnnouncementJob::class,
                fn (SendTournamentAnnouncementJob $job): bool => $job->userId === $outsider->id,
            );
        }
    });
});

describe('how fast it goes out', function (): void {
    it('is throttled, on the limiter the other bulk mailings share', function (): void {
        $job = new SendTournamentAnnouncementJob(1, 1);

        expect($job->middleware())->toHaveCount(1)
            ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class);

        expect(app(RateLimiter::class)->limiter('invitations'))->not->toBeNull();
    });

    it('skips a member archived between the fan-out and the send', function (): void {
        Notification::fake();

        dispatch_sync(new SendTournamentAnnouncementJob(announcedTournament()->id, 99999));

        Notification::assertNothingSent();
    });

    it('sends the announcement when the member is still there', function (): void {
        Notification::fake();

        $member = activeMember(makeActiveSeason());
        $tournament = announcedTournament();

        dispatch_sync(new SendTournamentAnnouncementJob($tournament->id, $member->id));

        Notification::assertSentTo($member, NewTournamentPublishedNotification::class);
    });
});

/*
 * The subject used to be built by concatenation inside __(), which made a
 * different translation key per tournament — so no key ever matched and the mail
 * went out in English. Nobody saw it because the mail was never sent.
 */
it('names the tournament through a placeholder, not a bespoke key', function (): void {
    $member = activeMember(makeActiveSeason());
    $tournament = announcedTournament();

    $mail = new NewTournamentPublishedNotification($tournament, $member)->toMail($member);

    expect($mail->subject)->toContain('Tournoi des crêpes')
        ->and($mail->subject)->not->toContain(':name');
});
