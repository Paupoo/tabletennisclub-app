<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Events\Interclub\TeamCreated;
use App\Domains\Shared\Events\Meetings\MeetingCreated;
use App\Domains\Shared\Events\Subscriptions\SubscriptionCreated;
use Illuminate\Support\Facades\Notification;

test('subscription created event triggers notification', function () {
    Notification::fake();

    $user = User::factory()->create();
    $subscription = Subscription::factory()->for($user)->create();

    event(new SubscriptionCreated($subscription));

    Notification::assertSentTo($user, \App\Domains\Subscriptions\Notifications\SubscriptionCreatedNotification::class);
});

test('meeting created event notifies all members for general assembly', function () {
    Notification::fake();

    $user1 = User::factory()->create(['is_active' => true]);
    $user2 = User::factory()->create(['is_active' => true]);

    $meeting = Meeting::factory()->create(['type' => MeetingTypeEnum::GENERAL_ASSEMBLY]);

    event(new MeetingCreated($meeting));

    Notification::assertSentTo($user1, \App\Domains\Meetings\Notifications\MeetingInvitationNotification::class);
    Notification::assertSentTo($user2, \App\Domains\Meetings\Notifications\MeetingInvitationNotification::class);
});

test('meeting created event notifies committee only for committee meeting', function () {
    Notification::fake();

    $member = User::factory()->create(['is_committee_member' => true, 'is_active' => true]);
    $regular = User::factory()->create(['is_committee_member' => false, 'is_active' => true]);

    $meeting = Meeting::factory()->create(['type' => MeetingTypeEnum::COMMITTEE]);

    event(new MeetingCreated($meeting));

    Notification::assertSentTo($member, \App\Domains\Meetings\Notifications\MeetingInvitationNotification::class);
    Notification::assertNotSentTo($regular, \App\Domains\Meetings\Notifications\MeetingInvitationNotification::class);
});

test('team created event notifies admins', function () {
    Notification::fake();

    $season = Season::factory()->create();
    $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
    $regular = User::factory()->create(['is_admin' => false, 'is_active' => true]);

    $team = Team::factory()->for($season)->create();

    event(new TeamCreated($team));

    Notification::assertSentTo($admin, \App\Domains\Competitions\Interclub\Notifications\TeamCreatedNotification::class);
    Notification::assertNotSentTo($regular, \App\Domains\Competitions\Interclub\Notifications\TeamCreatedNotification::class);
});
