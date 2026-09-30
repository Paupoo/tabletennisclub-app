<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingActionItem;
use App\Domains\Meetings\Models\MeetingMinutes;
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
    // It used to send a note taker to the writing desk and a member to a page
    // without the minutes: everyone now lands on the reading page, which
    // MeetingPolicy::readMinutes() guards.
    test('every reader is sent to the reading page', function (User $reader): void {
        $meeting = Meeting::factory()->generalAssembly()->completed()->create();
        MeetingMinutes::factory()->published()->for($meeting)->create();

        $mail = new MeetingMinutesNotification($meeting)->toMail($reader);

        expect($mail->actionUrl)->toBe(route('meetings.minutes.read', $meeting));
    })->with([
        'note taker' => fn (): User => User::factory()->withRole(Role::MEETINGS)->create(),
        'committee member' => fn (): User => User::factory()->isCommitteeMember()->create(),
        'member' => fn (): User => User::factory()->create(),
    ]);

    test('the bell notification points at the same page as the mail', function (): void {
        $meeting = Meeting::factory()->committee()->completed()->create();

        $payload = new MeetingMinutesNotification($meeting)->toArray(User::factory()->create());

        expect($payload['url'])->toBe(route('meetings.minutes.read', $meeting));
    });

    test('the mail carries the decisions, the reader\'s actions and the minutes as a PDF', function (): void {
        $reader = User::factory()->isCommitteeMember()->create();
        $meeting = Meeting::factory()->committee()->completed()->create(['scheduled_at' => '2026-03-12 20:00']);
        MeetingMinutes::factory()->published()->for($meeting)->create(['decisions' => ['On garde **le prix**']]);
        MeetingActionItem::factory()->for($meeting)->create(['title' => 'Réserver la salle', 'assigned_to_id' => $reader->id, 'due_date' => now()->subDays(2), 'is_completed' => false]);
        MeetingActionItem::factory()->for($meeting)->create(['title' => 'Tâche d\'un autre', 'assigned_to_id' => User::factory(), 'is_completed' => false]);

        $mail = new MeetingMinutesNotification($meeting)->toMail($reader);
        $html = (string) $mail->render();

        expect($html)->toContain('>le prix</strong>')
            ->toContain('Réserver la salle')
            ->not->toContain('Tâche d')
            ->and($mail->rawAttachments)->toHaveCount(1)
            ->and($mail->rawAttachments[0]['name'])->toBe('PV-comite-2026-03-12.pdf')
            ->and($mail->rawAttachments[0]['data'])->toStartWith('%PDF');
    });
});
