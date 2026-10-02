<?php

declare(strict_types=1);

use App\Actions\User\SendRenewalRemindersAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Subscriptions\Notifications\RenewalReminderNotification;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use App\Jobs\SendRenewalReminderJob;
use Illuminate\Cache\RateLimiter;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

pest()->group('club-admin', 'users', 'invitations');

/*
| The reminder to last season's members who have not registered again. It goes
| to the "to follow up" members of a selection only, never twice in a week, and
| through the same limiter as every other club-wide mailing.
*/

beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->previous = Season::factory()->create([
        'start_at' => now()->subYear()->startOfYear(),
        'end_at' => now()->subYear()->endOfYear(),
    ]);

    $this->silent = renewalMember($this->previous, 'Silence-Estival');
    $this->quiet = renewalMember($this->previous, 'Pas-Encore');

    $this->renewed = renewalMember($this->previous, 'Deja-Revenu');
    Subscription::factory()->for($this->renewed)->for($this->season)->create(['status' => 'confirmed', 'is_competitive' => false]);
});

/**
 * A member affiliated last season only, with an address of their own.
 */
function renewalMember(Season $previous, string $lastName): User
{
    $member = User::factory()->create([
        'last_name' => $lastName,
        'email' => mb_strtolower($lastName) . '@example.com',
        'birthdate' => now()->subYears(30),
        'renewal_reminded_at' => null,
    ]);

    Subscription::factory()->for($member)->for($previous)->create(['status' => 'paid', 'is_competitive' => false]);

    return $member;
}

describe('the queued send', function (): void {
    it('goes through the limiter every club-wide mailing shares', function (): void {
        $job = new SendRenewalReminderJob($this->silent->id);

        expect($job->middleware())->toHaveCount(1)
            ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class)
            ->and(class_uses_recursive($job))->toContain(RetriesWhileRateLimited::class)
            ->and(app(RateLimiter::class)->limiter('invitations'))->not->toBeNull();
    });

    it('writes to the member', function (): void {
        Notification::fake();

        new SendRenewalReminderJob($this->silent->id)->handle();

        Notification::assertSentTo($this->silent, RenewalReminderNotification::class);
    });

    it('stays silent for a member who registered while the reminder waited its turn', function (): void {
        Notification::fake();

        Subscription::factory()->for($this->silent)->for($this->season)->create(['status' => 'pending', 'is_competitive' => false]);

        new SendRenewalReminderJob($this->silent->id)->handle();

        Notification::assertNothingSent();
    });

    it('skips a member archived in the meantime', function (): void {
        Notification::fake();

        $this->silent->delete();

        new SendRenewalReminderJob($this->silent->id)->handle();

        Notification::assertNothingSent();
    });
});

describe('the message', function (): void {
    it('names the season and links to the page where the member registers', function (): void {
        $mail = new RenewalReminderNotification($this->season)->toMail($this->silent);

        expect($mail->subject)->toContain($this->season->name)
            ->and($mail->actionUrl)->toBe(route('admin.user.registration-management', $this->silent));
    });

    it('reaches a managed child through the parent who holds the account', function (): void {
        $parent = User::factory()->create(['email' => 'parent-relance@example.com']);
        $child = User::factory()->create(['email' => null, 'birthdate' => now()->subYears(11)]);
        $guardian = Guardian::factory()->create(['user_id' => $parent->id, 'email' => 'parent-relance@example.com']);
        $child->guardians()->attach($guardian);

        $mail = new RenewalReminderNotification($this->season)->toMail($child->fresh());

        expect($child->fresh()->routeNotificationForMail())->toBe(['parent-relance@example.com'])
            ->and($mail->actionUrl)->toBe(route('admin.user.registration-management', $parent));
    });

    it('counts a member without an address as reachable through a guardian', function (): void {
        Bus::fake();

        $guardian = Guardian::factory()->create(['email' => 'tuteur-relance@example.com']);
        $this->quiet->forceFill(['email' => null])->save();
        $this->quiet->guardians()->attach($guardian);

        $result = SendRenewalRemindersAction::handle([$this->quiet->id]);

        expect($result['queued'])->toBe(1);
    });
});
