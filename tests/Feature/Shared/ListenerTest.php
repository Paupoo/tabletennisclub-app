<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Notifications\TeamCreatedNotification;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingInvitationNotification;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Events\Interclub\TeamCreated;
use App\Domains\Shared\Events\Meetings\MeetingCreated;
use App\Domains\Shared\Events\Subscriptions\SubscriptionCreated;
use App\Domains\Subscriptions\Notifications\SubscriptionCreatedNotification;
use App\Jobs\SendMeetingInvitationJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

test('subscription created event triggers notification', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $subscription = Subscription::factory()->for($user)->create();

    event(new SubscriptionCreated($subscription));

    Notification::assertSentTo($user, SubscriptionCreatedNotification::class);
});

test('meeting created event notifies all members for general assembly', function (): void {
    Notification::fake();

    $season = makeActiveSeason();
    $user1 = User::factory()->create([]);
    $user2 = User::factory()->create([]);
    Subscription::factory()->for($user1)->create(['season_id' => $season->id, 'status' => 'confirmed']);
    Subscription::factory()->for($user2)->create(['season_id' => $season->id, 'status' => 'confirmed']);

    $meeting = Meeting::factory()->create(['type' => MeetingTypeEnum::GENERAL_ASSEMBLY]);

    event(new MeetingCreated($meeting));

    Notification::assertSentTo($user1, MeetingInvitationNotification::class);
    Notification::assertSentTo($user2, MeetingInvitationNotification::class);
});

/*
 * The convocations must leave through the throttled job, not in one blast: a
 * general assembly goes to every active member, and fifty near identical mails
 * in three seconds is what gets the club classified as a spammer (issue #69).
 */
test('meeting created event fans the general assembly out onto the throttled job', function (): void {
    Bus::fake([SendMeetingInvitationJob::class]);

    $season = makeActiveSeason();
    $members = User::factory()->count(3)->create([]);
    foreach ($members as $member) {
        Subscription::factory()->for($member)->create(['season_id' => $season->id, 'status' => 'confirmed']);
    }

    $meeting = Meeting::factory()->create(['type' => MeetingTypeEnum::GENERAL_ASSEMBLY]);

    event(new MeetingCreated($meeting));

    Bus::assertDispatchedTimes(SendMeetingInvitationJob::class, 3);
});

test('meeting created event notifies committee only for committee meeting', function (): void {
    Notification::fake();

    $member = User::factory()->isCommitteeMember()->create();
    $regular = User::factory()->create();

    $meeting = Meeting::factory()->create(['type' => MeetingTypeEnum::COMMITTEE]);

    event(new MeetingCreated($meeting));

    Notification::assertSentTo($member, MeetingInvitationNotification::class);
    Notification::assertNotSentTo($regular, MeetingInvitationNotification::class);
});

test('team created event notifies admins', function (): void {
    Notification::fake();

    $season = Season::factory()->create();
    $admin = User::factory()->isAdmin()->create();
    $regular = User::factory()->create();

    $team = Team::factory()->for($season)->create();

    event(new TeamCreated($team));

    Notification::assertSentTo($admin, TeamCreatedNotification::class);
    Notification::assertNotSentTo($regular, TeamCreatedNotification::class);
});
