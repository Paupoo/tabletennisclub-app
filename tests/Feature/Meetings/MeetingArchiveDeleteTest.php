<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── Helpers ────────────────────────────────────────────────────────────────────
function archiveAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'is_committee_member' => true]);
}

function archiveMember(): User
{
    return User::factory()->create(['is_admin' => false, 'is_committee_member' => false]);
}

/** A meeting with at least one sent invitation. */
function invitedMeeting(User $admin): Meeting
{
    $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);
    $meeting->users()->attach(
        archiveMember()->id,
        ['status' => MeetingUserStatusEnum::INVITED->value, 'invitation_sent_at' => now()]
    );

    return $meeting;
}

// ── Eligibility rules (model) ───────────────────────────────────────────────────
describe('Meeting deletion eligibility', function (): void {
    test('a draft meeting with no invitation sent can be deleted', function (): void {
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => archiveAdmin()->id]);

        expect($meeting->canBeDeleted())->toBeTrue();
    });

    test('a meeting with a sent invitation cannot be deleted', function (): void {
        $meeting = invitedMeeting(archiveAdmin());

        expect($meeting->canBeDeleted())->toBeFalse();
    });

    test('a completed meeting cannot be deleted', function (): void {
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => archiveAdmin()->id]);

        expect($meeting->canBeDeleted())->toBeFalse();
    });
});

describe('Meeting archive eligibility', function (): void {
    test('completed, cancelled and postponed meetings can be archived', function (string $state): void {
        $meeting = Meeting::factory()->committee()->create([
            'status' => $state,
            'created_by' => archiveAdmin()->id,
        ]);

        expect($meeting->canBeArchived())->toBeTrue();
    })->with([
        MeetingStatusEnum::COMPLETED->value,
        MeetingStatusEnum::CANCELLED->value,
        MeetingStatusEnum::POSTPONED->value,
    ]);

    test('planning and confirmed meetings cannot be archived', function (string $state): void {
        $meeting = Meeting::factory()->committee()->create([
            'status' => $state,
            'created_by' => archiveAdmin()->id,
        ]);

        expect($meeting->canBeArchived())->toBeFalse();
    })->with([
        MeetingStatusEnum::PLANNING->value,
        MeetingStatusEnum::CONFIRMED->value,
    ]);

    test('an already archived meeting cannot be archived again', function (): void {
        $meeting = Meeting::factory()->committee()->completed()->create([
            'created_by' => archiveAdmin()->id,
            'archived_at' => now(),
        ]);

        expect($meeting->isArchived())->toBeTrue()
            ->and($meeting->canBeArchived())->toBeFalse();
    });
});

// ── Model behaviour ─────────────────────────────────────────────────────────────
describe('Meeting archive/unarchive behaviour', function (): void {
    test('archiving a meeting archives a published event post and unarchiving republishes it', function (): void {
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => archiveAdmin()->id]);
        $post = EventPost::factory()->published()->create([
            'eventable_type' => $meeting->getMorphClass(),
            'eventable_id' => $meeting->id,
        ]);

        $meeting->archive();

        expect($meeting->fresh()->archived_at)->not->toBeNull()
            ->and($post->fresh()->status)->toBe(EventPostStatusEnum::ARCHIVED);

        $meeting->unarchive();

        expect($meeting->fresh()->archived_at)->toBeNull()
            ->and($post->fresh()->status)->toBe(EventPostStatusEnum::PUBLISHED);
    });

    test('archiving leaves a draft event post untouched', function (): void {
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => archiveAdmin()->id]);
        $post = EventPost::factory()->create([
            'status' => EventPostStatusEnum::DRAFT,
            'eventable_type' => $meeting->getMorphClass(),
            'eventable_id' => $meeting->id,
        ]);

        $meeting->archive();

        expect($post->fresh()->status)->toBe(EventPostStatusEnum::DRAFT);
    });

    test('deleting a meeting cascades its event post', function (): void {
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => archiveAdmin()->id]);
        $post = EventPost::factory()->create([
            'eventable_type' => $meeting->getMorphClass(),
            'eventable_id' => $meeting->id,
        ]);

        $meeting->delete();

        expect(EventPost::find($post->id))->toBeNull();
    });
});

// ── Show page actions ───────────────────────────────────────────────────────────
describe('Meeting show — delete', function (): void {
    test('admin can delete a draft meeting', function (): void {
        $admin = archiveAdmin();
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('deleteMeeting')
            ->assertRedirect(route('admin.meetings.index'));

        expect(Meeting::find($meeting->id))->toBeNull();
    });

    test('a meeting with invitations sent is not deleted', function (): void {
        $admin = archiveAdmin();
        $meeting = invitedMeeting($admin);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('deleteMeeting');

        expect(Meeting::find($meeting->id))->not->toBeNull();
    });

    test('a regular member cannot delete a meeting', function (): void {
        $meeting = Meeting::factory()->committee()->planning()->create(['created_by' => archiveAdmin()->id]);

        Livewire::actingAs(archiveMember())
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('deleteMeeting')
            ->assertForbidden();

        expect(Meeting::find($meeting->id))->not->toBeNull();
    });
});

describe('Meeting show — archive', function (): void {
    test('admin can archive a completed meeting', function (): void {
        $admin = archiveAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('archiveMeeting')
            ->assertRedirect(route('admin.meetings.index'));

        expect($meeting->fresh()->archived_at)->not->toBeNull();
    });

    test('a confirmed upcoming meeting is not archived', function (): void {
        $admin = archiveAdmin();
        $meeting = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('archiveMeeting');

        expect($meeting->fresh()->archived_at)->toBeNull();
    });

    test('admin can unarchive a meeting', function (): void {
        $admin = archiveAdmin();
        $meeting = Meeting::factory()->committee()->completed()->create([
            'created_by' => $admin->id,
            'archived_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('unarchiveMeeting');

        expect($meeting->fresh()->archived_at)->toBeNull();
    });

    test('a regular member cannot archive a meeting', function (): void {
        $meeting = Meeting::factory()->committee()->completed()->create(['created_by' => archiveAdmin()->id]);

        Livewire::actingAs(archiveMember())
            ->test('pages::club-events.meetings.show', ['meeting' => $meeting])
            ->call('archiveMeeting')
            ->assertForbidden();

        expect($meeting->fresh()->archived_at)->toBeNull();
    });
});

// ── Index page ──────────────────────────────────────────────────────────────────
describe('Meeting index — archived visibility', function (): void {
    test('archived meetings are hidden by default and shown when toggled', function (): void {
        $admin = archiveAdmin();
        $active = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);
        $archived = Meeting::factory()->committee()->completed()->create([
            'created_by' => $admin->id,
            'archived_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.index')
            ->assertSeeText($active->title)
            ->assertDontSeeText($archived->title)
            ->set('showArchived', true)
            ->assertSeeText($archived->title)
            ->assertDontSeeText($active->title);
    });
});

describe('Meeting index — bulk actions', function (): void {
    test('bulk archive only archives eligible meetings', function (): void {
        $admin = archiveAdmin();
        $completed = Meeting::factory()->committee()->completed()->create(['created_by' => $admin->id]);
        $confirmed = Meeting::factory()->committee()->confirmed()->create(['created_by' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.index')
            ->set('selected', [(string) $completed->id, (string) $confirmed->id])
            ->call('bulkArchive');

        expect($completed->fresh()->archived_at)->not->toBeNull()
            ->and($confirmed->fresh()->archived_at)->toBeNull();
    });

    test('bulk delete only deletes eligible meetings', function (): void {
        $admin = archiveAdmin();
        $draft = Meeting::factory()->committee()->planning()->create(['created_by' => $admin->id]);
        $invited = invitedMeeting($admin);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.index')
            ->set('selected', [(string) $draft->id, (string) $invited->id])
            ->call('bulkDelete');

        expect(Meeting::find($draft->id))->toBeNull()
            ->and(Meeting::find($invited->id))->not->toBeNull();
    });

    test('bulk unarchive restores archived meetings', function (): void {
        $admin = archiveAdmin();
        $archived = Meeting::factory()->committee()->completed()->create([
            'created_by' => $admin->id,
            'archived_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.meetings.index')
            ->set('showArchived', true)
            ->set('selected', [(string) $archived->id])
            ->call('bulkUnarchive');

        expect($archived->fresh()->archived_at)->toBeNull();
    });
});
