<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingCancelledNotification;
use App\Domains\Meetings\Notifications\MeetingDatePollNotification;
use App\Domains\Meetings\Notifications\MeetingInvitationNotification;
use App\Domains\Meetings\Notifications\MeetingMinutesNotification;
use App\Domains\Meetings\Notifications\MeetingPostponedNotification;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Meeting mails are rendered in French', function (): void {
    test('the invitation mail is fully translated', function (): void {
        $user = User::factory()->create(['first_name' => 'Aurélien']);
        $meeting = Meeting::factory()->committee()->confirmed()->physical()
            ->withMeal('Pizzas', 1200)->withQuorum(8)
            ->create(['title' => 'Réunion de comité', 'created_by' => $user->id]);

        $mail = new MeetingInvitationNotification($meeting)->toMail($user);

        // French puts a non-breaking space before a colon, so these assertions
        // carry U+00A0 rather than a plain space — see the typography pass.
        expect($mail->subject)->toContain("Invitation\u{A0}: Réunion de comité")
            ->and($mail->greeting)->toContain('Bonjour Aurélien')
            ->and(implode(' ', $mail->introLines))
            ->toContain('Vous êtes invité à')
            ->toContain("**Lieu\u{A0}:**")
            ->toContain("**Repas\u{A0}:**")
            ->toContain("**Quorum requis\u{A0}:**")
            ->and($mail->actionText)->not->toContain('Respond to the invitation');
    });

    test('the date poll mail is fully translated', function (): void {
        $user = User::factory()->create(['first_name' => 'Marie']);
        $meeting = Meeting::factory()->committee()->planning()
            ->create(['title' => 'AG extraordinaire', 'created_by' => $user->id]);
        $meeting->dateProposals()->create(['proposed_at' => now()->addWeek()]);

        $mail = new MeetingDatePollNotification($meeting)->toMail($user);

        $text = $mail->subject . ' ' . $mail->greeting . ' ' . implode(' ', $mail->introLines);
        expect($text)->not->toContain('We need your availability');
    });

    test('cancellation, postponement and minutes mails are fully translated', function (): void {
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->committee()->confirmed()
            ->create(['title' => 'Réunion test', 'created_by' => $user->id]);

        $cancelled = new MeetingCancelledNotification($meeting)->toMail($user);
        $postponed = new MeetingPostponedNotification($meeting)->toMail($user);
        $minutes = new MeetingMinutesNotification($meeting)->toMail($user);

        foreach ([$cancelled, $postponed, $minutes] as $mail) {
            $text = $mail->subject . ' ' . implode(' ', $mail->introLines);
            expect($text)->not->toContain('has been')
                ->not->toContain('The meeting')
                ->not->toContain('are now available');
        }
    });
});

describe('The minutes mail points at the minutes', function (): void {
    test('a note taker is sent straight to the minutes page', function (): void {
        $noteTaker = User::factory()->withRole(Role::MEETINGS)->create();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $noteTaker->id]);

        $mail = new MeetingMinutesNotification($meeting)->toMail($noteTaker);

        expect($mail->actionUrl)->toBe(route('admin.meetings.minutes', $meeting));
    });

    test('the bell notification points at the same page as the mail', function (): void {
        $noteTaker = User::factory()->withRole(Role::MEETINGS)->create();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $noteTaker->id]);

        $payload = new MeetingMinutesNotification($meeting)->toArray($noteTaker);

        expect($payload['url'])->toBe(route('admin.meetings.minutes', $meeting));
    });

    test('a committee member keeps the meeting page, which renders the minutes inline', function (): void {
        // The minutes page writes on every action and aborts on `meetings.view`
        // alone: sending the committee there would be issue #39 all over again.
        $committee = User::factory()->isCommitteeMember()->create();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $committee->id]);

        $mail = new MeetingMinutesNotification($meeting)->toMail($committee);

        expect($mail->actionUrl)->toBe(route('admin.meetings.show', $meeting));
    });

    test('an ordinary member still lands in their own space', function (): void {
        $member = User::factory()->create();
        $meeting = Meeting::factory()->generalAssembly()->completed()->create(['created_by' => $member->id]);

        $mail = new MeetingMinutesNotification($meeting)->toMail($member);

        expect($mail->actionUrl)->toBe(route('admin.user.event-subscription', $member))
            ->and($mail->actionUrl)->not->toContain('/minutes');
    });
});
