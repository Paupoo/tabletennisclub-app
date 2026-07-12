<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingMinutesNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function minutesAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'is_committee_member' => true]);
}

describe('Minutes page — drafting', function (): void {
    test('announcements, decisions and notes are saved as a draft on blur', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->set('announcements', ['Nouvelle salle dès septembre'])
            ->set('decisions', ['Budget buvette approuvé'])
            ->set('notes', 'RAS');

        $minutes = $meeting->fresh()->minutes;
        expect($minutes)->not->toBeNull()
            ->and($minutes->announcements)->toContain('Nouvelle salle dès septembre')
            ->and($minutes->decisions)->toContain('Budget buvette approuvé')
            ->and($minutes->notes)->toBe('RAS')
            ->and($minutes->is_published)->toBeFalse();
    });

    test('action items are saved as part of the draft', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->set('actionItems', [[
                'title' => 'Réserver la salle',
                'description' => '',
                'assigned_to_id' => (string) $admin->id,
                'due_date' => now()->addWeek()->format('Y-m-d'),
                'is_completed' => false,
            ]]);

        $items = $meeting->fresh()->actionItems;
        expect($items)->toHaveCount(1)
            ->and($items->first()->title)->toBe('Réserver la salle')
            ->and($items->first()->assigned_to_id)->toBe($admin->id);
    });

    test('attendance can be recorded from the minutes page', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $member = User::factory()->create([]);
        $meeting->users()->attach($member->id, ['status' => 'confirmed']);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->call('markAttended', $member->id);

        expect($meeting->users()->where('users.id', $member->id)->first()->registration->status->value)
            ->toBe('attended');
    });

    test('a regular member cannot open the minutes page', function (): void {
        $member = User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => minutesAdmin()->id]);

        Livewire::actingAs($member)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->assertForbidden();
    });
});

describe('Minutes page — publish & send', function (): void {
    test('publishing persists the draft and marks it published', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->set('decisions', ['Décision A'])
            ->call('publishMinutes');

        $minutes = $meeting->fresh()->minutes;
        expect($minutes->is_published)->toBeTrue()
            ->and($minutes->published_by)->toBe($admin->id)
            ->and($minutes->decisions)->toContain('Décision A');
    });

    test('minutes cannot be sent before being published', function (): void {
        Notification::fake();

        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->call('sendMinutes', false);

        expect($meeting->fresh()->minutes?->sent_to_committee_at)->toBeNull();
        Notification::assertNothingSent();
    });

    test('published minutes can be sent to the committee', function (): void {
        Notification::fake();

        $admin = minutesAdmin();
        $committee = User::factory()->create(['is_committee_member' => true]);
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->set('decisions', ['Décision A'])
            ->call('publishMinutes')
            ->call('sendMinutes', false);

        expect($meeting->fresh()->minutes->sent_to_committee_at)->not->toBeNull();
        Notification::assertSentTo(
            $committee,
            MeetingMinutesNotification::class,
        );
    });
});
