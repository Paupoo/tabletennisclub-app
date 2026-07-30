<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use Livewire\Livewire;

describe('EventPostButton mount — meeting prefill', function (): void {
    it('pre-fills location and description from a physical meeting when no event post exists', function (): void {
        $admin = User::factory()->create();
        $meeting = Meeting::factory()->committee()->confirmed()->physical()->create([
            'location' => 'Salle Océane',
            'description' => 'Réunion mensuelle du comité.',
        ]);

        Livewire::actingAs($admin)
            ->test('admin.shared.event-post-button', [
                'modelClass' => Meeting::class,
                'modelId' => $meeting->id,
                'eventType' => 'MEETING',
                'icon' => '📋',
                'eventDate' => $meeting->scheduled_at?->toDateString(),
                'startTime' => $meeting->scheduled_at?->format('H:i:s'),
                'endTime' => $meeting->ends_at?->format('H:i:s'),
                'defaultTitle' => $meeting->title,
                'defaultLocation' => $meeting->location,
                'defaultDescription' => $meeting->description,
                'canPublish' => true,
            ])
            ->assertSet('eventLocation', 'Salle Océane')
            ->assertSet('eventDescription', 'Réunion mensuelle du comité.');
    });

    it('pre-fills location from a virtual meeting link', function (): void {
        $admin = User::factory()->create();
        $meeting = Meeting::factory()->committee()->confirmed()->virtual()->create([
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
        ]);

        Livewire::actingAs($admin)
            ->test('admin.shared.event-post-button', [
                'modelClass' => Meeting::class,
                'modelId' => $meeting->id,
                'eventType' => 'MEETING',
                'icon' => '📋',
                'eventDate' => $meeting->scheduled_at?->toDateString(),
                'startTime' => $meeting->scheduled_at?->format('H:i:s'),
                'endTime' => $meeting->ends_at?->format('H:i:s'),
                'defaultTitle' => $meeting->title,
                'defaultLocation' => $meeting->meeting_link,
                'canPublish' => true,
            ])
            ->assertSet('eventLocation', 'https://meet.google.com/abc-defg-hij');
    });

    it('does not override an already published event post with the meeting defaults', function (): void {
        $admin = User::factory()->create();
        $meeting = Meeting::factory()->committee()->confirmed()->physical()->create(['location' => 'Salle Océane']);

        Livewire::actingAs($admin)
            ->test('admin.shared.event-post-button', [
                'modelClass' => Meeting::class,
                'modelId' => $meeting->id,
                'eventType' => 'MEETING',
                'icon' => '📋',
                'defaultTitle' => $meeting->title,
                'defaultLocation' => $meeting->location,
                'canPublish' => true,
            ])
            ->set('eventLocation', 'Bar du club — changement de dernière minute')
            ->call('saveEventPost', 'draft');

        Livewire::actingAs($admin)
            ->test('admin.shared.event-post-button', [
                'modelClass' => Meeting::class,
                'modelId' => $meeting->id,
                'eventType' => 'MEETING',
                'icon' => '📋',
                'defaultTitle' => $meeting->title,
                'defaultLocation' => $meeting->location,
                'canPublish' => true,
            ])
            ->assertSet('eventLocation', 'Bar du club — changement de dernière minute');
    });
});
