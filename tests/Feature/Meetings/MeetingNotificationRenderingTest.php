<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingCancelledNotification;
use App\Domains\Meetings\Notifications\MeetingDatePollNotification;
use App\Domains\Meetings\Notifications\MeetingInvitationNotification;
use App\Domains\Meetings\Notifications\MeetingMinutesNotification;
use App\Domains\Meetings\Notifications\MeetingPostponedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Meeting mails are rendered in French', function (): void {
    test('the invitation mail is fully translated', function (): void {
        $user = User::factory()->create(['first_name' => 'Aurélien']);
        $meeting = Meeting::factory()->committee()->confirmed()->physical()
            ->withMeal('Pizzas', 1200)->withQuorum(8)
            ->create(['title' => 'Réunion de comité', 'created_by' => $user->id]);

        $mail = new MeetingInvitationNotification($meeting)->toMail($user);

        expect($mail->subject)->toContain('Invitation : Réunion de comité')
            ->and($mail->greeting)->toContain('Bonjour Aurélien')
            ->and(implode(' ', $mail->introLines))
            ->toContain('Vous êtes invité à')
            ->toContain('**Lieu :**')
            ->toContain('**Repas :**')
            ->toContain('**Quorum requis :**')
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
