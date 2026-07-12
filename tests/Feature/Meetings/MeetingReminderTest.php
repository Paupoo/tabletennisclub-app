<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingInvitationNotification;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function reminderAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'is_committee_member' => true]);
}

describe('Meeting invitation reminders', function (): void {
    test('reminds invitees without a response when last invitation is older than 48 hours', function (): void {
        Notification::fake();

        $admin = reminderAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);

        $pending = User::factory()->create([]);
        $confirmed = User::factory()->create([]);
        $meeting->users()->attach($pending->id, [
            'status' => MeetingUserStatusEnum::INVITED->value,
            'invitation_sent_at' => now()->subDays(3),
        ]);
        $meeting->users()->attach($confirmed->id, [
            'status' => MeetingUserStatusEnum::CONFIRMED->value,
            'invitation_sent_at' => now()->subDays(3),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('remindPendingInvitees');

        Notification::assertSentTo($pending, MeetingInvitationNotification::class);
        Notification::assertNotSentTo($confirmed, MeetingInvitationNotification::class);

        $sentAt = $meeting->users()->where('users.id', $pending->id)->first()->registration->invitation_sent_at;
        expect($sentAt->isAfter(now()->subMinute()))->toBeTrue();
    });

    test('does not remind an invitee already contacted less than 48 hours ago', function (): void {
        Notification::fake();

        $admin = reminderAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);

        $recent = User::factory()->create([]);
        $meeting->users()->attach($recent->id, [
            'status' => MeetingUserStatusEnum::INVITED->value,
            'invitation_sent_at' => now()->subDay(),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('remindPendingInvitees');

        Notification::assertNothingSent();
    });

    test('a regular member cannot send reminders', function (): void {
        Notification::fake();

        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => reminderAdmin()->id]);
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
        $meeting->users()->attach($member->id, [
            'status' => MeetingUserStatusEnum::INVITED->value,
            'invitation_sent_at' => now()->subDays(3),
        ]);

        Livewire::actingAs($member)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('remindPendingInvitees')
            ->assertForbidden();

        Notification::assertNothingSent();
    });
});
