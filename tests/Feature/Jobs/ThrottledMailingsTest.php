<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\NewTournamentPublishedNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentInvitationNotification;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Jobs\SendMeetingInvitationJob;
use App\Jobs\SendMemberInvitationJob;
use App\Jobs\SendTournamentAnnouncementJob;
use App\Jobs\SendTournamentCancellationJob;
use App\Jobs\SendTournamentInvitationJob;
use App\Jobs\SendTournamentUpdateJob;
use Illuminate\Cache\RateLimiter as RateLimiterStore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

pest()->group('notifications');

/*
 * A limiter does not hold a job back, it releases it — and a release counts as
 * an attempt. Under `composer dev`, whose worker runs with `--tries=1`, every
 * mail past the first window was therefore killed on its return with
 * MaxAttemptsExceededException, before handle() ever ran. The throttle was not
 * spreading the mailing, it was dropping most of it, and saying nothing.
 */
it('spreads a mailing over the windows instead of dropping it, under --tries=1', function (): void {
    config()->set('queue.default', 'database');
    RateLimiter::for('invitations', fn (): Limit => Limit::perMinute(1));
    Notification::fake();

    $season = makeActiveSeason();
    $first = activeMember($season);
    $second = activeMember($season);
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);

    SendTournamentAnnouncementJob::dispatch($tournament->id, $first->id);
    SendTournamentAnnouncementJob::dispatch($tournament->id, $second->id);

    $worker = app('queue.worker');
    $options = new WorkerOptions(maxTries: 1);

    $worker->runNextJob('database', 'default', $options);    // fills the window
    $worker->runNextJob('database', 'default', $options);    // finds it full

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(1);          // waiting, not dead

    Notification::assertSentTo($first, NewTournamentPublishedNotification::class);
    Notification::assertNotSentTo($second, NewTournamentPublishedNotification::class);

    $this->travel(90)->seconds();                           // past the window it was released to
    $worker->runNextJob('database', 'default', $options);

    Notification::assertSentTo($second, NewTournamentPublishedNotification::class);
    expect(DB::table('jobs')->count())->toBe(0);
});

it('bounds every throttled mailing by a deadline rather than by a count', function (): void {
    $mailings = [
        new SendTournamentAnnouncementJob(1, 1),
        new SendMemberInvitationJob(1),
        new SendMeetingInvitationJob(1, 1),
        new SendTournamentInvitationJob(1, 1),
        new SendTournamentCancellationJob(1, 1),
        new SendTournamentUpdateJob(1, 1, ['time']),
    ];

    foreach ($mailings as $mailing) {
        expect($mailing->retryUntil()->getTimestamp())->toBeGreaterThan(now()->addHours(5)->getTimestamp())
            ->and($mailing->maxExceptions)->toBe(3);        // a real fault still fails fast
    }
});

/*
 * Every club-wide mailing has to go out through a declared limiter — the point
 * of the shared `invitations` key is that the non-urgent mailings queue behind
 * each other rather than adding up. A job that forgets its middleware still
 * passes every test about what it sends, so the middleware is asserted here.
 */
it('runs every club-wide mailing through a declared limiter', function (): void {
    $expected = [
        [new SendMemberInvitationJob(1), 'invitations'],
        [new SendTournamentAnnouncementJob(1, 1), 'invitations'],
        [new SendTournamentInvitationJob(1, 1), 'invitations'],
        [new SendMeetingInvitationJob(1, 1), 'convocations'],
        [new SendTournamentCancellationJob(1, 1), 'convocations'],
        [new SendTournamentUpdateJob(1, 1, ['time']), 'convocations'],
    ];

    foreach ($expected as [$mailing, $limiter]) {
        $middleware = $mailing->middleware();

        expect($middleware)->toHaveCount(1)
            ->and($middleware[0])->toBeInstanceOf(RateLimited::class)
            ->and(app(RateLimiterStore::class)->limiter($limiter))->not->toBeNull();
    }
});

/*
 * The invitation notification is itself ShouldQueue, so a job calling notify()
 * would only push a second, unthrottled job: the limiter would pace the
 * dispatching and the hundred and forty three mails would still leave together.
 * The job has to be the thing that sends.
 */
it('sends the tournament invitation inside the throttled job, not from a second one', function (): void {
    Notification::fake();

    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);
    $member = User::factory()->create();

    (new SendTournamentInvitationJob($tournament->id, $member->id, 'Bring water'))->handle();

    Notification::assertSentTo(
        $member,
        TournamentInvitationNotification::class,
        fn (TournamentInvitationNotification $notification): bool => $notification->customMessage === 'Bring water',
    );
});

it('skips a member archived between the fan-out and the send', function (): void {
    Notification::fake();

    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);

    (new SendTournamentInvitationJob($tournament->id, 99999))->handle();

    Notification::assertNothingSent();
});
