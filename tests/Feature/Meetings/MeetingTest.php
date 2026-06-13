<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingCancelledNotification;
use App\Domains\Meetings\Notifications\MeetingDatePollNotification;
use App\Domains\Meetings\Notifications\MeetingInvitationNotification;
use App\Domains\Meetings\Notifications\MeetingPostponedNotification;
use App\Domains\Shared\Enums\MeetingFormatEnum;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use App\Jobs\SendMeetingInvitationsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────────────────────
function meetingAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'is_committee_member' => true]);
}

function meetingMember(): User
{
    return User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
}

function confirmedMeeting(?User $creator = null): Meeting
{
    $creator ??= meetingAdmin();

    return Meeting::factory()->committee()->confirmed()->create(['created_by' => $creator->id]);
}

// ── Model ─────────────────────────────────────────────────────────────────────
describe('Meeting model', function (): void {
    test('quorum percentage calculates correctly', function (): void {
        $admin = meetingAdmin();
        $meeting = Meeting::factory()->generalAssembly()->confirmed()->withQuorum(10)->create(['created_by' => $admin->id]);
        $members = User::factory()->count(6)->create([]);

        foreach ($members as $member) {
            $meeting->users()->attach($member->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value]);
        }

        expect($meeting->confirmedCount())->toBe(6)
            ->and($meeting->quorumPercentage())->toBe(60.0)
            ->and($meeting->isQuorumReached())->toBeFalse();
    });

    test('quorum is reached when confirmed count >= quorum', function (): void {
        $admin = meetingAdmin();
        $meeting = Meeting::factory()->generalAssembly()->confirmed()->withQuorum(5)->create(['created_by' => $admin->id]);
        $members = User::factory()->count(5)->create([]);

        foreach ($members as $member) {
            $meeting->users()->attach($member->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value]);
        }

        expect($meeting->isQuorumReached())->toBeTrue()
            ->and($meeting->quorumPercentage())->toBe(100.0);
    });

    test('quorum is always reached when no quorum set', function (): void {
        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => meetingAdmin()->id]);
        expect($meeting->isQuorumReached())->toBeTrue();
    });

    test('isInPollPhase returns true only when planning with proposals', function (): void {
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => meetingAdmin()->id]);
        expect($meeting->isInPollPhase())->toBeFalse();

        $meeting->dateProposals()->create(['proposed_at' => now()->addWeek()]);
        expect($meeting->fresh()->isInPollPhase())->toBeTrue();
    });

    test('meal_price accessor returns euros from cents', function (): void {
        $meeting = Meeting::factory()->committee()->confirmed()
            ->withMeal('Pizzas', 1200)
            ->create(['created_by' => meetingAdmin()->id]);

        expect($meeting->meal_price)->toBe(12.0);
    });

});

// ── Index page ────────────────────────────────────────────────────────────────
describe('Meeting index page', function (): void {
    test('admin can access meeting index', function (): void {
        Livewire::actingAs(meetingAdmin())
            ->test('pages::club-events.meetings.index')
            ->assertStatus(200);
    });

    test('regular member gets 403 on meeting index', function (): void {
        $this->actingAs(meetingMember())
            ->get(route('admin.meetings.index'))
            ->assertStatus(403);
    });

    test('index lists meetings with search', function (): void {
        $admin = meetingAdmin();
        $meeting = confirmedMeeting($admin);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.index')
            ->set('search', $meeting->title)
            ->assertSeeText($meeting->title);
    });

    test('index shows filter-specific empty state when filters yield no results', function (): void {
        $admin = meetingAdmin();
        Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.index')
            ->set('type', MeetingTypeEnum::GENERAL_ASSEMBLY->value)
            ->assertSeeText('No meetings match your filters');
    });

    test('index shows generic empty state when no meetings exist at all', function (): void {
        Livewire::actingAs(meetingAdmin())
            ->test('pages::club-events.meetings.index')
            ->assertSeeText('No meetings yet');
    });

    test('index filters by type', function (): void {
        $admin = meetingAdmin();
        $committee = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);
        $ga = Meeting::factory()->generalAssembly()->confirmed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.index')
            ->set('type', MeetingTypeEnum::COMMITTEE->value)
            ->assertSeeText($committee->title)
            ->assertDontSeeText($ga->title);
    });
});

// ── Form (create / edit) ──────────────────────────────────────────────────────
describe('Meeting form — fixed date mode', function (): void {
    test('admin can create a meeting with a fixed date', function (): void {
        Bus::fake();

        $admin = meetingAdmin();

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.form')
            ->set('title', 'Réunion test')
            ->set('type', MeetingTypeEnum::COMMITTEE->value)
            ->set('format', MeetingFormatEnum::PHYSICAL->value)
            ->set('location', 'Salle du club')
            ->set('datePollMode', false)
            ->set('scheduledAt', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('endsAt', now()->addWeek()->addHour()->format('Y-m-d\TH:i'))
            ->set('agendaItems', [['title' => 'Point 1', 'description' => '']])
            ->set('step', '3')
            ->call('save');

        $meeting = Meeting::where('title', 'Réunion test')->first();
        expect($meeting)->not->toBeNull()
            ->and($meeting->scheduled_at)->not->toBeNull();
    });

    test('creating with fixed date auto-dispatches invitation job', function (): void {
        Bus::fake();

        $admin = meetingAdmin();

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.form')
            ->set('title', 'AG rentrée')
            ->set('type', MeetingTypeEnum::COMMITTEE->value)
            ->set('format', MeetingFormatEnum::PHYSICAL->value)
            ->set('location', 'Salle du club')
            ->set('datePollMode', false)
            ->set('scheduledAt', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('endsAt', now()->addWeek()->addHour()->format('Y-m-d\TH:i'))
            ->set('agendaItems', [['title' => 'Point 1', 'description' => '']])
            ->set('step', '3')
            ->call('save');

        Bus::assertDispatched(SendMeetingInvitationsJob::class);
    });

    test('editing does NOT re-dispatch invitation job', function (): void {
        Bus::fake();

        $admin = meetingAdmin();
        $meeting = confirmedMeeting($admin);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.form', ['meeting' => $meeting])
            ->set('title', 'Titre modifié')
            ->set('step', '3')
            ->call('save');

        Bus::assertNotDispatched(SendMeetingInvitationsJob::class);
        expect($meeting->fresh()->title)->toBe('Titre modifié');
    });

    test('nextStep advances to review step', function (): void {
        $admin = meetingAdmin();

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.form')
            ->set('title', 'Test meeting')
            ->set('type', MeetingTypeEnum::COMMITTEE->value)
            ->set('format', MeetingFormatEnum::PHYSICAL->value)
            ->set('location', 'Salle du club')
            ->set('datePollMode', false)
            ->set('scheduledAt', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('endsAt', now()->addWeek()->addHour()->format('Y-m-d\TH:i'))
            ->set('step', '1')
            ->call('nextStep')
            ->assertSet('step', '2')
            ->set('agendaItems', [['title' => 'Point 1', 'description' => '']])
            ->call('nextStep')
            ->assertSet('step', '3');
    });

    test('prevStep goes back from review step', function (): void {
        $admin = meetingAdmin();

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.form')
            ->set('title', 'Test meeting')
            ->set('type', MeetingTypeEnum::COMMITTEE->value)
            ->set('format', MeetingFormatEnum::PHYSICAL->value)
            ->set('location', 'Salle du club')
            ->set('datePollMode', false)
            ->set('scheduledAt', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('endsAt', now()->addWeek()->addHour()->format('Y-m-d\TH:i'))
            ->set('agendaItems', [['title' => 'Point 1', 'description' => '']])
            ->set('step', '3')
            ->call('prevStep')
            ->assertSet('step', '2');
    });

    test('switching to poll mode clears scheduled date', function (): void {
        $admin = meetingAdmin();

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.form')
            ->set('scheduledAt', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('datePollMode', true)
            ->assertSet('scheduledAt', null)
            ->assertSet('endsAt', null)
            ->assertSet('rsvpDeadline', null);
    });
});

describe('Meeting form — poll mode', function (): void {
    test('creating with poll mode auto-sends date poll to committee', function (): void {
        Notification::fake();

        $admin = meetingAdmin();
        $member = User::factory()->create(['is_committee_member' => true]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.form')
            ->set('title', 'Réunion sondage')
            ->set('type', MeetingTypeEnum::COMMITTEE->value)
            ->set('format', MeetingFormatEnum::VIRTUAL->value)
            ->set('meetingLink', 'https://meet.example.com')
            ->set('datePollMode', true)
            ->set('agendaItems', [['title' => 'Point 1', 'description' => '']])
            ->set('dateProposals', [
                ['proposed_at' => now()->addWeeks(1)->format('Y-m-d\TH:i')],
                ['proposed_at' => now()->addWeeks(2)->format('Y-m-d\TH:i')],
            ])
            ->set('step', '3')
            ->call('save');

        Notification::assertSentTo($member, MeetingDatePollNotification::class);
        Notification::assertSentTo($admin, MeetingDatePollNotification::class);
    });

    test('creating with poll mode does NOT dispatch invitation job', function (): void {
        Bus::fake();
        Notification::fake();

        $admin = meetingAdmin();

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.form')
            ->set('title', 'Réunion sondage')
            ->set('type', MeetingTypeEnum::COMMITTEE->value)
            ->set('format', MeetingFormatEnum::VIRTUAL->value)
            ->set('meetingLink', 'https://meet.example.com')
            ->set('datePollMode', true)
            ->set('agendaItems', [['title' => 'Point 1', 'description' => '']])
            ->set('dateProposals', [['proposed_at' => now()->addWeek()->format('Y-m-d\TH:i')]])
            ->set('step', '3')
            ->call('save');

        Bus::assertNotDispatched(SendMeetingInvitationsJob::class);
    });

    test('nextStep goes to review step in poll mode', function (): void {
        $admin = meetingAdmin();

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.form')
            ->set('title', 'Test meeting')
            ->set('type', MeetingTypeEnum::COMMITTEE->value)
            ->set('format', MeetingFormatEnum::VIRTUAL->value)
            ->set('meetingLink', 'https://meet.example.com')
            ->set('datePollMode', true)
            ->set('step', '1')
            ->call('nextStep')
            ->assertSet('step', '2')
            ->set('agendaItems', [['title' => 'Point 1', 'description' => '']])
            ->call('nextStep')
            ->assertSet('step', '3');
    });

    test('switching to fixed date mode clears date proposals', function (): void {
        $admin = meetingAdmin();

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.form')
            ->set('datePollMode', true)
            ->set('dateProposals', [['proposed_at' => now()->addWeek()->format('Y-m-d\TH:i')]])
            ->set('datePollMode', false)
            ->assertSet('dateProposals', []);
    });

    test('date proposals are saved when in poll mode', function (): void {
        Bus::fake();
        Notification::fake();

        $admin = meetingAdmin();

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.form')
            ->set('title', 'Réunion avec proposals')
            ->set('type', MeetingTypeEnum::COMMITTEE->value)
            ->set('format', MeetingFormatEnum::VIRTUAL->value)
            ->set('meetingLink', 'https://meet.example.com')
            ->set('datePollMode', true)
            ->set('agendaItems', [['title' => 'Point 1', 'description' => '']])
            ->set('dateProposals', [
                ['proposed_at' => now()->addWeeks(1)->format('Y-m-d\TH:i')],
                ['proposed_at' => now()->addWeeks(2)->format('Y-m-d\TH:i')],
            ])
            ->set('step', '3')
            ->call('save');

        $meeting = Meeting::where('title', 'Réunion avec proposals')->first();
        expect($meeting)->not->toBeNull()
            ->and($meeting->dateProposals)->toHaveCount(2)
            ->and($meeting->scheduled_at)->toBeNull();
    });
});

describe('Meeting form — misc', function (): void {
    test('meeting title is required', function (): void {
        Livewire::actingAs(meetingAdmin())
            ->test('pages::club-events.meetings.form')
            ->set('step', '3')
            ->call('save')
            ->assertHasErrors(['title' => 'required']);
    });

    test('agenda items are saved with meeting', function (): void {
        Bus::fake();

        $admin = meetingAdmin();

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.form')
            ->set('title', 'Réunion avec agenda')
            ->set('type', MeetingTypeEnum::COMMITTEE->value)
            ->set('format', MeetingFormatEnum::VIRTUAL->value)
            ->set('meetingLink', 'https://meet.example.com/test')
            ->set('datePollMode', false)
            ->set('scheduledAt', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('endsAt', now()->addWeek()->addHour()->format('Y-m-d\TH:i'))
            ->set('agendaItems', [
                ['title' => 'Point 1', 'description' => ''],
                ['title' => 'Point 2', 'description' => 'Détails'],
            ])
            ->set('step', '3')
            ->call('save');

        $meeting = Meeting::where('title', 'Réunion avec agenda')->first();
        expect($meeting)->not->toBeNull()
            ->and($meeting->agendaItems)->toHaveCount(2);
    });

    test('quorum field appears only for general assemblies', function (): void {
        Livewire::actingAs(meetingAdmin())
            ->test('pages::club-events.meetings.form')
            ->set('type', MeetingTypeEnum::COMMITTEE->value)
            ->assertDontSee('Quorum')
            ->set('type', MeetingTypeEnum::GENERAL_ASSEMBLY->value)
            ->assertSee('Quorum');
    });

    test('mount detects poll mode from existing meeting', function (): void {
        $admin = meetingAdmin();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);
        $meeting->dateProposals()->create(['proposed_at' => now()->addWeek()]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.form', ['meeting' => $meeting])
            ->assertSet('datePollMode', true);
    });

    test('mount detects fixed date mode from existing meeting', function (): void {
        $admin = meetingAdmin();
        $meeting = confirmedMeeting($admin);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.form', ['meeting' => $meeting])
            ->assertSet('datePollMode', false);
    });
});

// ── SendMeetingInvitationsJob ─────────────────────────────────────────────────
describe('SendMeetingInvitationsJob', function (): void {
    test('job sends invitation to committee members and updates pivot', function (): void {
        Notification::fake();

        $admin = meetingAdmin();
        $member = User::factory()->create(['is_committee_member' => true]);
        $meeting = confirmedMeeting($admin);

        dispatch_sync(new SendMeetingInvitationsJob($meeting->id));

        Notification::assertSentTo($member, MeetingInvitationNotification::class);
        Notification::assertSentTo($admin, MeetingInvitationNotification::class);

        expect($meeting->users()->where('users.id', $member->id)->exists())->toBeTrue();
    });

    test('job updates pivot status to invited', function (): void {
        Notification::fake();

        $admin = meetingAdmin();
        $member = User::factory()->create(['is_committee_member' => true]);
        $meeting = confirmedMeeting($admin);

        dispatch_sync(new SendMeetingInvitationsJob($meeting->id));

        $pivot = $meeting->users()->where('users.id', $member->id)->first()->registration;
        expect($pivot->status)->toBe(MeetingUserStatusEnum::INVITED)
            ->and($pivot->invitation_sent_at)->not->toBeNull();
    });

    test('job re-invites GA to all active members', function (): void {
        Notification::fake();

        $admin = meetingAdmin();
        $season = makeActiveSeason();
        $members = User::factory()->count(3)->create([]);
        foreach ($members as $member) {
            Subscription::factory()->for($member)->create(['season_id' => $season->id, 'status' => 'confirmed']);
        }
        $meeting = Meeting::factory()->generalAssembly()->confirmed()->create(['created_by' => $admin->id]);

        dispatch_sync(new SendMeetingInvitationsJob($meeting->id));

        foreach ($members as $member) {
            Notification::assertSentTo($member, MeetingInvitationNotification::class);
        }
    });
});

// ── Show (control center) ─────────────────────────────────────────────────────
describe('Meeting show page', function (): void {
    test('admin can view a meeting', function (): void {
        $admin = meetingAdmin();
        $meeting = confirmedMeeting($admin);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->assertStatus(200)
            ->assertSeeText($meeting->title);
    });

    test('sendInvitations dispatches job for confirmed meeting', function (): void {
        Bus::fake();

        $admin = meetingAdmin();
        $meeting = confirmedMeeting($admin);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('sendInvitations');

        Bus::assertDispatched(SendMeetingInvitationsJob::class, fn ($job) => $job->meetingId === $meeting->id);
    });

    test('invitations cannot be dispatched if meeting is in planning status', function (): void {
        Bus::fake();

        $admin = meetingAdmin();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('sendInvitations');

        Bus::assertNotDispatched(SendMeetingInvitationsJob::class);
    });

    test('cancel notifies invited users when pivot has entries', function (): void {
        Notification::fake();

        $admin = meetingAdmin();
        $member = meetingMember();
        $meeting = confirmedMeeting($admin);
        $meeting->users()->attach($member->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->set('cancellationNote', 'Annulée pour force majeure.')
            ->call('cancelMeeting');

        expect($meeting->fresh()->status)->toBe(MeetingStatusEnum::CANCELLED);
        Notification::assertSentTo($member, MeetingCancelledNotification::class);
    });

    test('cancel falls back to committee when no invitations sent yet', function (): void {
        Notification::fake();

        $admin = meetingAdmin();
        $committee = User::factory()->create(['is_committee_member' => true]);
        $meeting = confirmedMeeting($admin);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->set('cancellationNote', 'Annulée.')
            ->call('cancelMeeting');

        expect($meeting->fresh()->status)->toBe(MeetingStatusEnum::CANCELLED);
        Notification::assertSentTo($committee, MeetingCancelledNotification::class);
        Notification::assertSentTo($admin, MeetingCancelledNotification::class);
    });

    test('postpone notifies invited users when pivot has entries', function (): void {
        Notification::fake();

        $admin = meetingAdmin();
        $member = meetingMember();
        $meeting = confirmedMeeting($admin);
        $meeting->users()->attach($member->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->set('postponedNote', 'Report pour conflit de calendrier.')
            ->call('postponeMeeting');

        expect($meeting->fresh()->status)->toBe(MeetingStatusEnum::POSTPONED);
        Notification::assertSentTo($member, MeetingPostponedNotification::class);
    });

    test('postpone falls back to committee when no invitations sent yet', function (): void {
        Notification::fake();

        $admin = meetingAdmin();
        $committee = User::factory()->create(['is_committee_member' => true]);
        $meeting = confirmedMeeting($admin);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->set('postponedNote', 'Report.')
            ->call('postponeMeeting');

        expect($meeting->fresh()->status)->toBe(MeetingStatusEnum::POSTPONED);
        Notification::assertSentTo($committee, MeetingPostponedNotification::class);
        Notification::assertSentTo($admin, MeetingPostponedNotification::class);
    });

    test('admin can mark a user as attended', function (): void {
        $admin = meetingAdmin();
        $member = meetingMember();
        $meeting = Meeting::factory()->committee()->create([
            'status' => MeetingStatusEnum::CONFIRMED,
            'scheduled_at' => now()->subHour(),
            'ends_at' => now(),
            'created_by' => $admin->id,
        ]);
        $meeting->users()->attach($member->id, ['status' => MeetingUserStatusEnum::INVITED->value]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('markAttended', $member->id);

        $pivot = $meeting->users()->where('users.id', $member->id)->first()->registration;
        expect($pivot->status)->toBe(MeetingUserStatusEnum::ATTENDED);
    });
});

// ── Minutes ───────────────────────────────────────────────────────────────────
describe('Meeting minutes', function (): void {
    test('admin can publish minutes', function (): void {
        $admin = meetingAdmin();
        $meeting = confirmedMeeting($admin);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->set('minutesAnnouncements', ['Annonce test'])
            ->set('minutesDecisions', ['Décision A', 'Décision B'])
            ->set('minutesNotes', 'Notes libres')
            ->call('publishMinutes');

        $minutes = $meeting->fresh()->minutes;
        expect($minutes)->not->toBeNull()
            ->and($minutes->is_published)->toBeTrue()
            ->and($minutes->announcements)->toContain('Annonce test')
            ->and($minutes->decisions)->toHaveCount(2);
    });

    test('minutes cannot be sent before being published', function (): void {
        Notification::fake();

        $admin = meetingAdmin();
        $meeting = confirmedMeeting($admin);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('sendMinutes', false);

        expect($meeting->fresh()->minutes?->sent_to_committee_at)->toBeNull();
        Notification::assertNothingSent();
    });
});

describe('Meeting attendance — catering view', function (): void {
    test('the attendance tab shows the catering banner and per-attendee meal badges', function (): void {
        $admin = meetingAdmin();
        $meeting = Meeting::factory()->confirmed()->withMeal('Pizzas', 1200)->create(['created_by' => $admin->id]);

        $reserver = User::factory()->create(['last_name' => 'Aaa']);
        $skipper = User::factory()->create(['last_name' => 'Bbb']);

        $meeting->users()->attach($reserver->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value, 'meal_reserved' => true]);
        $meeting->users()->attach($skipper->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value, 'meal_reserved' => false]);

        $meeting->users()->where('users.id', $reserver->id)->first()->registration
            ->payment()->create([
                'reference' => '001/2026/09001',
                'amount_due' => 12,
                'amount_paid' => 0,
                'status' => 'pending',
            ]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->set('activeTab', 'attendance')
            ->assertSee(__('Catering'))
            ->assertSee('12,00')
            ->assertSee(__('Meal · pending'))
            ->assertSee(__('No meal'));
    });

    test('the catering banner is hidden when the meeting has no meal', function (): void {
        $admin = meetingAdmin();
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);
        $user = User::factory()->create([]);
        $meeting->users()->attach($user->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->set('activeTab', 'attendance')
            ->assertDontSee(__('Catering'));
    });
});
