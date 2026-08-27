<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\TournamentRegistrationConfirmedNotification;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingInvitationNotification;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackRequestedNotification;

/*
 * Issue #39: a member clicked the bell on a notification about their own
 * training pack and got a 403. The link pointed at `admin.trainings.index`,
 * which `can:trainings.manage` reserves for the committee.
 *
 * These tests follow the link the way the member does, and assert the page
 * actually opens — a URL that merely looks like my-space proves nothing.
 */

function trainingNotificationUrl(User $member): string
{
    // No season passed on purpose: the factory reuses whatever season exists, and
    // forcing a fresh one here collided with the pack's own (seasons may not
    // overlap). The link does not depend on the season anyway.
    $subscription = Subscription::factory()->for($member)->create();

    return (new TrainingPackRequestedNotification(TrainingPack::factory()->create(), $subscription))
        ->toArray($member)['url'];
}

describe('a member notification', function (): void {
    it('sends a member to their own trainings page, and it opens', function (): void {
        $member = User::factory()->create();

        $url = trainingNotificationUrl($member);

        expect($url)->toBe(route('admin.user.registration-management', $member));

        $this->actingAs($member)->get($url)->assertOk();
    });

    it('sends a member to their own events page for a tournament, and it opens', function (): void {
        $member = User::factory()->create();
        $tournament = Tournament::factory()->create();

        $url = (new TournamentRegistrationConfirmedNotification($tournament))->toArray($member)['url'];

        expect($url)->toBe(route('admin.user.event-subscription', $member));

        $this->actingAs($member)->get($url)->assertOk();
    });

    /*
     * The exact click the issue was filed for: the old link is still a route, so
     * the only way to show the bug is gone is to prove the member is no longer
     * sent anywhere they would be refused.
     */
    it('no longer sends them where they would be refused', function (): void {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('admin.trainings.index'))->assertForbidden();

        expect(trainingNotificationUrl($member))->not->toBe(route('admin.trainings.index'));
    });
});

/*
 * A managed member has no address of their own, which is exactly what says they
 * have no login either: the mail went to a guardian. Sending that guardian to
 * the ward's my-space would be a 403 again — every my-space page is self-only.
 */
describe('a managed member notification', function (): void {
    it('points at the guardian who can actually open it', function (): void {
        $guardianAccount = User::factory()->create(['email' => 'parent@example.com']);
        $ward = User::factory()->create(['email' => null]);
        $ward->guardians()->attach(
            Guardian::factory()->create(['email' => 'parent@example.com', 'user_id' => $guardianAccount->id])
        );

        $url = trainingNotificationUrl($ward->fresh());

        expect($url)->toBe(route('admin.user.registration-management', $guardianAccount));

        $this->actingAs($guardianAccount)->get($url)->assertOk();
    });

    it('falls back to the ward when no guardian holds an account', function (): void {
        $ward = User::factory()->create(['email' => null]);
        $ward->guardians()->attach(Guardian::factory()->create(['user_id' => null]));

        expect(trainingNotificationUrl($ward->fresh()))
            ->toBe(route('admin.user.registration-management', $ward));
    });
});

/*
 * A general assembly is convened to every active member, so the meeting link
 * cannot be the back-office page alone — but the committee loses nothing.
 */
describe('a meeting convocation', function (): void {
    it('keeps the committee on the back-office meeting page', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();
        $meeting = Meeting::factory()->create();

        expect((new MeetingInvitationNotification($meeting))->toArray($committee)['url'])
            ->toBe(route('admin.meetings.show', $meeting));
    });

    it('sends an ordinary member to their own events page, and it opens', function (): void {
        $member = User::factory()->create();
        $meeting = Meeting::factory()->create();

        $url = (new MeetingInvitationNotification($meeting))->toArray($member)['url'];

        expect($url)->toBe(route('admin.user.event-subscription', $member));

        $this->actingAs($member)->get($url)->assertOk();
    });
});
