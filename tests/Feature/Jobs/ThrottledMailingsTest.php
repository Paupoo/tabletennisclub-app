<?php

declare(strict_types=1);

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\NewTournamentPublishedNotification;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Jobs\SendMeetingInvitationJob;
use App\Jobs\SendMemberInvitationJob;
use App\Jobs\SendTournamentAnnouncementJob;
use Illuminate\Cache\RateLimiting\Limit;
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
    ];

    foreach ($mailings as $mailing) {
        expect($mailing->retryUntil()->getTimestamp())->toBeGreaterThan(now()->addHours(5)->getTimestamp())
            ->and($mailing->maxExceptions)->toBe(3);        // a real fault still fails fast
    }
});
