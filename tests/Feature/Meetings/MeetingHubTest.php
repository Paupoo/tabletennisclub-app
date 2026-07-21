<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingDatePollNotification;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function hubAdmin(): User
{
    return User::factory()->isAdmin()->isCommitteeMember()->create();
}

describe('Meeting hub — next step banner', function (): void {
    test('a planning meeting with proposals and no vote suggests sending the poll', function (): void {
        $admin = hubAdmin();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);
        $meeting->dateProposals()->create(['proposed_at' => now()->addWeek()]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->assertSeeText(__('Next step'))
            ->assertSeeText(__('Send the date poll to the committee'));
    });

    test('sending the poll records it and shows a discreet sent state', function (): void {
        Notification::fake();

        $admin = hubAdmin();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);
        $meeting->dateProposals()->create(['proposed_at' => now()->addWeek()]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('sendDatePoll')
            ->assertSeeText(__('Poll sent on :date', ['date' => now()->translatedFormat('d M · H\hi')]));

        expect($meeting->fresh()->poll_sent_at)->not->toBeNull();
    });

    test('the poll cannot be re-sent within 48 hours', function (): void {
        Notification::fake();

        $admin = hubAdmin();
        $meeting = Meeting::factory()->committee()->planning()->create([
            'created_by' => $admin->id,
            'poll_sent_at' => now()->subDay(),
        ]);
        $meeting->dateProposals()->create(['proposed_at' => now()->addWeek()]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('sendDatePoll');

        Notification::assertNothingSent();
    });

    test('the poll can be re-sent after 48 hours', function (): void {
        Notification::fake();

        $admin = hubAdmin();
        $meeting = Meeting::factory()->committee()->planning()->create([
            'created_by' => $admin->id,
            'poll_sent_at' => now()->subDays(3),
        ]);
        $meeting->dateProposals()->create(['proposed_at' => now()->addWeek()]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('sendDatePoll');

        Notification::assertSentTo(
            $admin,
            MeetingDatePollNotification::class,
        );
    });

    test('the leading date proposal is subtly highlighted', function (): void {
        $admin = hubAdmin();
        $voterA = hubAdmin();
        $voterB = hubAdmin();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);
        $leading = $meeting->dateProposals()->create(['proposed_at' => now()->addWeek()]);
        $trailing = $meeting->dateProposals()->create(['proposed_at' => now()->addWeeks(2)]);

        $leading->votes()->create(['user_id' => $voterA->id, 'vote' => 'available']);
        $leading->votes()->create(['user_id' => $voterB->id, 'vote' => 'available']);
        $trailing->votes()->create(['user_id' => $voterA->id, 'vote' => 'unavailable']);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->assertSeeText(__('Leading option'));
    });

    test('a planning meeting with votes suggests picking the final date', function (): void {
        $admin = hubAdmin();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);
        $proposal = $meeting->dateProposals()->create(['proposed_at' => now()->addWeek()]);
        $proposal->votes()->create(['user_id' => $admin->id, 'vote' => 'available']);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->assertSeeText(__(':n votes received — pick the final date', ['n' => 1]));
    });

    test('a confirmed future meeting without invitations suggests sending them', function (): void {
        $admin = hubAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->physical()->create(['created_by' => $admin->id]);
        $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Budget']);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->assertSeeText(__('Date confirmed — invite the members'));
    });

    test('a confirmed meeting with pending invitees suggests a reminder', function (): void {
        $admin = hubAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);
        $pending = User::factory()->create([]);
        $meeting->users()->attach($pending->id, [
            'status' => MeetingUserStatusEnum::INVITED->value,
            'invitation_sent_at' => now()->subDays(3),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->assertSeeText(__('Send a reminder'));
    });

    test('a confirmed meeting in the past suggests marking it completed', function (): void {
        $admin = hubAdmin();
        $meeting = Meeting::factory()->committee()->create([
            'status' => MeetingStatusEnum::CONFIRMED,
            'scheduled_at' => now()->subDay(),
            'ends_at' => now()->subDay()->addHours(2),
            'created_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->assertSeeText(__('This meeting took place'));
    });

    test('a confirmed future meeting already offers to prepare the minutes', function (): void {
        $admin = hubAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->assertSeeText(__('Prepare the minutes'));
    });

    test('a completed meeting without published minutes suggests writing them', function (): void {
        $admin = hubAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->assertSeeText(__('Write and publish the minutes'));
    });

    test('a cancelled meeting shows no next step banner', function (): void {
        $admin = hubAdmin();
        $meeting = Meeting::factory()->committee()->create([
            'status' => MeetingStatusEnum::CANCELLED,
            'created_by' => $admin->id,
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->assertDontSeeText(__('Next step'));
    });

    test('a regular member never sees the next step banner', function (): void {
        $member = User::factory()->create();
        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => hubAdmin()->id]);

        Livewire::actingAs($member)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->assertDontSeeText(__('Next step'));
    });
});
