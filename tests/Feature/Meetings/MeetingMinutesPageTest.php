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
    return User::factory()->isAdmin()->isCommitteeMember()->create();
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
        $member = User::factory()->create();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => minutesAdmin()->id]);

        Livewire::actingAs($member)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->assertForbidden();
    });
});

describe('Minutes page — note-taking lock', function (): void {
    test('opening the page does not take the note-taking pen (I4)', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting]);

        // Merely opening to read must not claim the pen — the free pen stays free.
        expect($meeting->fresh()->minutes_editor_id)->toBeNull();
    });

    test('the first edit claims the pen', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        $component = Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting]);

        expect($meeting->fresh()->minutes_editor_id)->toBeNull();

        $component->set('notes', 'Première prise de notes');

        expect($meeting->fresh()->minutes_editor_id)->toBe($admin->id)
            ->and($meeting->fresh()->minutes?->notes)->toBe('Première prise de notes');
    });

    test('opening does not steal a stale lock; the first edit reclaims it (I4)', function (): void {
        $away = minutesAdmin();
        $arriving = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create([
            'created_by' => $away->id,
            'minutes_editor_id' => $away->id,
            'minutes_editor_at' => now()->subMinutes(20),
        ]);

        $component = Livewire::actingAs($arriving)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting]);

        // Opening a page whose previous holder went stale must not grab the pen.
        expect($meeting->fresh()->minutes_editor_id)->toBe($away->id);

        // Only when the arriving member actually writes do they reclaim it.
        $component->set('notes', 'Je reprends');
        expect($meeting->fresh()->minutes_editor_id)->toBe($arriving->id);
    });

    test('a second member sees who takes notes and their edits are not persisted', function (): void {
        $holder = minutesAdmin();
        $other = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $holder->id]);
        $meeting->acquireMinutesLock($holder);

        Livewire::actingAs($other)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->assertSeeText(__(':name is taking notes', ['name' => $holder->full_name]))
            ->set('notes', 'tentative pirate');

        expect($meeting->fresh()->minutes?->notes)->not->toBe('tentative pirate')
            ->and($meeting->fresh()->minutes_editor_id)->toBe($holder->id);
    });

    test('taking over transfers the lock and allows writing', function (): void {
        $holder = minutesAdmin();
        $other = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $holder->id]);
        $meeting->acquireMinutesLock($holder);

        Livewire::actingAs($other)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->call('takeOver')
            ->set('notes', 'notes reprises');

        $fresh = $meeting->fresh();
        expect($fresh->minutes_editor_id)->toBe($other->id)
            ->and($fresh->minutes->notes)->toBe('notes reprises');
    });

});

describe('Minutes page — live reading', function (): void {
    test('a read-only viewer picks up the note taker changes on each poll tick', function (): void {
        $holder = minutesAdmin();
        $viewer = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $holder->id]);
        $meeting->acquireMinutesLock($holder);
        $meeting->minutes()->create(['notes' => 'version initiale']);

        $component = Livewire::actingAs($viewer)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->assertSet('notes', 'version initiale');

        // The note taker keeps writing from their own session.
        $meeting->minutes->update(['notes' => 'version en direct', 'decisions' => ['Décision live']]);

        $component->call('syncDraft')
            ->assertSet('notes', 'version en direct')
            ->assertSet('decisions', ['Décision live']);
    });

    test('the note taker own draft is never overwritten by the poll', function (): void {
        $holder = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $holder->id]);
        $meeting->minutes()->create(['notes' => 'ancienne version']);

        Livewire::actingAs($holder)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->set('notes', 'je tape en ce moment')
            ->call('syncDraft')
            ->assertSet('notes', 'je tape en ce moment');
    });
});

describe('Minutes page — live reading (holder side)', function (): void {
    test('a holder who lost the pen sees the new note taker on the next poll tick', function (): void {
        $holder = minutesAdmin();
        $usurper = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $holder->id]);

        $component = Livewire::actingAs($holder)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->set('notes', 'je prends le stylo') // claim the pen by writing (I4)
            ->assertSeeText(__('You are taking the notes'));

        // Someone takes over from another session.
        $meeting->fresh()->acquireMinutesLock($usurper, force: true);

        $component->call('syncDraft')
            ->assertSeeText(__(':name is taking notes', ['name' => $usurper->full_name]));
    });
});

describe('Minutes page — live agenda', function (): void {
    test('an agenda item can be ticked as discussed and unticked', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $item = $meeting->agendaItems()->create(['sort_order' => 0, 'title' => 'Budget']);

        $component = Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->call('toggleDiscussed', $item->id);

        expect($item->fresh()->discussed_at)->not->toBeNull();

        $component->call('toggleDiscussed', $item->id);
        expect($item->fresh()->discussed_at)->toBeNull();
    });
});

describe('Minutes page — publish & send', function (): void {
    test('minutes of a meeting that has not started yet cannot be published', function (): void {
        $admin = minutesAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.minutes', ['meeting' => $meeting])
            ->set('decisions', ['Décision anticipée'])
            ->call('publishMinutes');

        expect($meeting->fresh()->minutes?->is_published ?? false)->toBeFalse();
    });

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
        $committee = User::factory()->isCommitteeMember()->create();
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
