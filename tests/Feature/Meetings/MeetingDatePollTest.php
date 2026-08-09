<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingDateProposal;
use App\Domains\Meetings\Models\MeetingDateVote;
use App\Domains\Meetings\Notifications\MeetingDatePollNotification;
use App\Domains\Shared\Enums\MeetingDateVoteEnum;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── Date poll (Livewire) ──────────────────────────────────────────────────────
describe('Date poll via Livewire', function (): void {
    test('admin can send date poll to committee', function (): void {
        Notification::fake();

        $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
        $member = User::factory()->isCommitteeMember()->create();

        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);
        $meeting->dateProposals()->create(['proposed_at' => now()->addWeek()]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('sendDatePoll');

        Notification::assertSentTo($member, MeetingDatePollNotification::class);
        Notification::assertSentTo($admin, MeetingDatePollNotification::class);
    });

    test('selecting a date proposal confirms the meeting', function (): void {
        $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);
        $proposal = $meeting->dateProposals()->create(['proposed_at' => now()->addWeeks(2)]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('selectDateProposal', $proposal->id);

        $fresh = $meeting->fresh();
        expect($fresh->status)->toBe(MeetingStatusEnum::CONFIRMED)
            ->and($fresh->scheduled_at)->not->toBeNull();

        expect(MeetingDateProposal::find($proposal->id)->is_selected)->toBeTrue();
    });

    test('poll cannot be sent without proposals', function (): void {
        Notification::fake();

        $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('sendDatePoll');

        Notification::assertNothingSent();
    });
});

// ── Date poll via signed URL (email link) ────────────────────────────────────
describe('Date poll via email link', function (): void {
    test('committee member can view poll page via signed URL', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->isCommitteeMember()->create();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);
        $meeting->dateProposals()->create(['proposed_at' => now()->addWeek()]);

        $url = URL::signedRoute('meetings.poll.vote', ['meeting' => $meeting->id, 'user' => $user->id]);

        $this->get($url)->assertStatus(200)->assertSee($meeting->title);
    });

    test('unsigned poll URL is rejected', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);

        $this->get(route('meetings.poll.vote', ['meeting' => $meeting->id, 'user' => $user->id]))
            ->assertStatus(403);
    });

    test('user can submit votes via signed URL', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->isCommitteeMember()->create();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);
        $proposal = $meeting->dateProposals()->create(['proposed_at' => now()->addWeek()]);

        $url = URL::signedRoute('meetings.poll.vote', ['meeting' => $meeting->id, 'user' => $user->id]);

        $this->post($url, ['votes' => [$proposal->id => MeetingDateVoteEnum::AVAILABLE->value]])
            ->assertRedirect();

        $vote = MeetingDateVote::where('user_id', $user->id)
            ->where('meeting_date_proposal_id', $proposal->id)
            ->first();

        expect($vote)->not->toBeNull()
            ->and($vote->vote)->toBe(MeetingDateVoteEnum::AVAILABLE);
    });

    test('votes can be updated (upsert)', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->isCommitteeMember()->create();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);
        $proposal = $meeting->dateProposals()->create(['proposed_at' => now()->addWeek()]);

        MeetingDateVote::create([
            'meeting_date_proposal_id' => $proposal->id,
            'user_id' => $user->id,
            'vote' => MeetingDateVoteEnum::AVAILABLE->value,
        ]);

        $url = URL::signedRoute('meetings.poll.vote', ['meeting' => $meeting->id, 'user' => $user->id]);

        $this->post($url, ['votes' => [$proposal->id => MeetingDateVoteEnum::UNAVAILABLE->value]]);

        $vote = MeetingDateVote::where('user_id', $user->id)
            ->where('meeting_date_proposal_id', $proposal->id)
            ->first();

        expect($vote->vote)->toBe(MeetingDateVoteEnum::UNAVAILABLE)
            ->and(MeetingDateVote::where('user_id', $user->id)->count())->toBe(1);
    });
});
