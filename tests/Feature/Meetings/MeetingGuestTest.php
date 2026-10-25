<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingMinutes;
use App\Domains\Meetings\Notifications\MeetingCancelledNotification;
use App\Domains\Meetings\Notifications\MeetingInvitationNotification;
use App\Domains\Meetings\Notifications\MeetingPostponedNotification;
use App\Domains\Meetings\Notifications\MeetingRsvpConfirmationNotification;
use App\Domains\Meetings\Services\MinutesReport;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** A confirmed committee meeting whose convocations already left, with two points. */
function guestReadyMeeting(User $committeeMember): Meeting
{
    $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => $committeeMember->id]);
    $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Budget des salles']);
    $meeting->agendaItems()->create(['sort_order' => 1, 'title' => 'Planning des entraînements']);
    $meeting->users()->attach($committeeMember->id, [
        'status' => MeetingUserStatusEnum::INVITED->value,
        'invitation_sent_at' => now(),
    ]);

    return $meeting;
}

describe('inviting a guest from the meeting page', function (): void {
    test('a coach who holds no affiliation can be found and invited for one point', function (): void {
        Notification::fake();

        $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
        $coach = User::factory()->isCoach()->create(['first_name' => 'Amaury', 'last_name' => 'Lambert']);
        $meeting = guestReadyMeeting($admin);
        $point = $meeting->agendaItems()->where('title', 'Planning des entraînements')->first();

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->set('guestSearch', 'Amau')
            ->assertSeeText('Amaury Lambert')
            ->set('guestPointId', (string) $point->id)
            ->call('inviteGuest', $coach->id)
            ->assertHasNoErrors();

        $registration = $meeting->users()->whereKey($coach->id)->first()->registration;

        expect($registration->is_guest)->toBeTrue()
            ->and($registration->guest_agenda_item_id)->toBe($point->id)
            ->and($registration->invitation_sent_at)->not->toBeNull();

        Notification::assertSentTo($coach, MeetingInvitationNotification::class);
    });
});

describe('the guest convocation', function (): void {
    test('a guest added before the convocations leaves with them, and keeps being a guest', function (): void {
        Notification::fake();

        $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
        $coach = User::factory()->isCoach()->create(['first_name' => 'Amaury']);
        $meeting = Meeting::factory()->committee()->confirmed()->physical()->create(['created_by' => $admin->id]);
        $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Budget des salles']);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('inviteGuest', $coach->id);

        Notification::assertNothingSentTo($coach);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('sendInvitations');

        Notification::assertSentTo($coach, MeetingInvitationNotification::class);
        Notification::assertSentTo($admin, MeetingInvitationNotification::class);
        expect($meeting->users()->whereKey($coach->id)->first()->registration)
            ->is_guest->toBeTrue()
            ->invitation_sent_at->not->toBeNull();
    });

    test('a committee member is never offered as a guest, and only a manager may invite one', function (): void {
        $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
        $colleague = User::factory()->isCommitteeMember()->create(['first_name' => 'Colette']);
        $coach = User::factory()->isCoach()->create(['first_name' => 'Coline']);
        $meeting = guestReadyMeeting($admin);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->set('guestSearch', 'Col')
            ->assertSeeText($coach->full_name)
            ->assertDontSeeText($colleague->full_name);

        Livewire::actingAs($colleague)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('inviteGuest', $coach->id)
            ->assertForbidden();
    });
});

describe('what a guest is told of the agenda', function (): void {
    /** @return array{0: Meeting, 1: User, 2: User, 3: User} meeting, committee member, guest for a point, guest for all of it */
    function guestFixture(): array
    {
        $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
        $meeting = guestReadyMeeting($admin);
        $meeting->update(['description' => 'Contexte : la dette de la buvette']);
        $point = $meeting->agendaItems()->where('title', 'Planning des entraînements')->first();
        $pointGuest = User::factory()->isCoach()->create();
        $wholeGuest = User::factory()->isCoach()->create();
        $meeting->users()->attach($pointGuest->id, ['status' => 'invited', 'is_guest' => true, 'guest_agenda_item_id' => $point->id]);
        $meeting->users()->attach($wholeGuest->id, ['status' => 'invited', 'is_guest' => true]);

        return [$meeting->fresh(), $admin, $pointGuest, $wholeGuest];
    }

    function guestInvitationText(Meeting $meeting, User $user): string
    {
        $mail = new MeetingInvitationNotification($meeting)->toMail($user);

        return implode("\n", [...$mail->introLines, ...$mail->outroLines]);
    }

    function guestIcsOf(MailMessage $mail): string
    {
        return $mail->rawAttachments[0]['data'];
    }

    test('the convocation names only the point a guest was asked for, and the committee keeps the whole agenda', function (): void {
        [$meeting, $admin, $pointGuest, $wholeGuest] = guestFixture();

        expect(guestInvitationText($meeting, $admin))
            ->toContain('Budget des salles')
            ->toContain('Planning des entraînements');

        expect(guestInvitationText($meeting, $pointGuest))
            ->toContain('Planning des entraînements')
            ->not->toContain('Budget des salles');

        expect(guestInvitationText($meeting, $wholeGuest))
            ->not->toContain('Budget des salles')
            ->not->toContain('Planning des entraînements');
    });

    test('the calendar files a guest receives carry neither the agenda nor the description', function (): void {
        [$meeting, $admin, $pointGuest] = guestFixture();

        foreach ([
            new MeetingCancelledNotification($meeting),
            new MeetingPostponedNotification($meeting),
            new MeetingRsvpConfirmationNotification($meeting),
        ] as $notification) {
            expect(guestIcsOf($notification->toMail($pointGuest)))
                ->toContain('Planning des entraînements')
                ->not->toContain('Budget des salles')
                ->not->toContain('buvette');

            expect(guestIcsOf($notification->toMail($admin)))->toContain('Budget des salles');
        }
    });

    test('the answer page shows a guest only their point', function (): void {
        [$meeting, , $pointGuest] = guestFixture();

        $this->get(URL::signedRoute('meetings.rsvp', ['meeting' => $meeting->id, 'user' => $pointGuest->id]))
            ->assertOk()
            ->assertSeeText('Planning des entraînements')
            ->assertDontSeeText('Budget des salles')
            ->assertDontSeeText('buvette');
    });
});

describe('guests in the minutes', function (): void {
    test('the minutes list the guests who came apart, never as absent, and a guest never makes the quorum', function (): void {
        $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
        $treasurer = User::factory()->isCommitteeMember()->create();
        $coach = User::factory()->isCoach()->create(['first_name' => 'Amaury', 'last_name' => 'Lambert']);
        $noShow = User::factory()->isCoach()->create(['first_name' => 'Gaston', 'last_name' => 'Retard']);
        $meeting = Meeting::factory()->committee()->completed()->withQuorum(2)->create(['created_by' => $admin->id]);
        $meeting->users()->attach($admin->id, ['status' => 'attended']);
        $meeting->users()->attach($treasurer->id, ['status' => 'absent']);
        $meeting->users()->attach($coach->id, ['status' => 'attended', 'is_guest' => true]);
        $meeting->users()->attach($noShow->id, ['status' => 'absent', 'is_guest' => true]);
        MeetingMinutes::factory()->published()->for($meeting)->create();

        $report = MinutesReport::for($meeting->fresh());

        expect($report->present->pluck('id')->all())->toBe([$admin->id])
            ->and($report->guests->pluck('id')->all())->toBe([$coach->id])
            ->and($report->absent->pluck('id')->all())->toBe([$treasurer->id])
            ->and($report->quorumReached())->toBeFalse();

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.reader', ['meeting' => $meeting])
            ->assertSeeText(__('Guests'))
            ->assertSeeText('Amaury Lambert')
            ->assertDontSeeText('Gaston Retard');
    });

    test('a guest who came does not count towards the quorum while the notes are taken', function (): void {
        $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
        $coach = User::factory()->isCoach()->create();
        $meeting = Meeting::factory()->committee()->completed()->withQuorum(2)->create(['created_by' => $admin->id]);
        $meeting->users()->attach($admin->id, ['status' => 'attended']);
        $meeting->users()->attach($coach->id, ['status' => 'attended', 'is_guest' => true]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->assertSeeText(__('Quorum :present/:quorum', ['present' => 1, 'quorum' => 2]))
            ->assertSeeText(__('Guest'));

        expect($meeting->fresh()->isQuorumReached())->toBeFalse();
    });

    test('a guest is never offered as the owner of an action', function (): void {
        $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
        $coach = User::factory()->isCoach()->create();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $meeting->users()->attach($coach->id, ['status' => 'attended', 'is_guest' => true]);

        $owners = Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->instance()->usersForAssignment;

        expect(collect($owners)->pluck('id'))->toContain($admin->id)->not->toContain($coach->id);
    });
});
