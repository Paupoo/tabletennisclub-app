<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Jobs\SendMeetingInvitationsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createAdmin(): User
{
    return User::factory()->isAdmin()->isCommitteeMember()->create();
}

describe('Meeting quick create', function (): void {
    test('a fixed-date meeting is created confirmed without sending anything', function (): void {
        Bus::fake();
        Notification::fake();

        Livewire::actingAs(createAdmin())
            ->test('pages::club-events.meetings.create')
            ->set('title', 'Réunion de comité')
            ->set('type', MeetingTypeEnum::COMMITTEE->value)
            ->set('scheduledAt', now()->addWeek()->format('Y-m-d\TH:i'))
            ->call('save')
            ->assertRedirect();

        $meeting = Meeting::where('title', 'Réunion de comité')->first();
        expect($meeting)->not->toBeNull()
            ->and($meeting->status)->toBe(MeetingStatusEnum::CONFIRMED)
            ->and($meeting->scheduled_at)->not->toBeNull()
            ->and($meeting->ends_at)->not->toBeNull();

        Bus::assertNotDispatched(SendMeetingInvitationsJob::class);
        Notification::assertNothingSent();
    });

    test('a poll meeting is created planning with its proposals, nothing sent', function (): void {
        Notification::fake();

        Livewire::actingAs(createAdmin())
            ->test('pages::club-events.meetings.create')
            ->set('title', 'AG de rentrée')
            ->set('type', MeetingTypeEnum::GENERAL_ASSEMBLY->value)
            ->set('dateMode', 'poll')
            ->set('dateProposals', [
                ['proposed_at' => now()->addWeek()->format('Y-m-d\TH:i')],
                ['proposed_at' => now()->addWeeks(2)->format('Y-m-d\TH:i')],
            ])
            ->call('save')
            ->assertRedirect();

        $meeting = Meeting::where('title', 'AG de rentrée')->first();
        expect($meeting->status)->toBe(MeetingStatusEnum::PLANNING)
            ->and($meeting->scheduled_at)->toBeNull()
            ->and($meeting->dateProposals)->toHaveCount(2);

        Notification::assertNothingSent();
    });

    test('a fixed-date meeting requires a date', function (): void {
        Livewire::actingAs(createAdmin())
            ->test('pages::club-events.meetings.create')
            ->set('title', 'Sans date')
            ->call('save')
            ->assertHasErrors(['scheduledAt']);

        expect(Meeting::count())->toBe(0);
    });

    test('a poll meeting requires at least one proposal', function (): void {
        Livewire::actingAs(createAdmin())
            ->test('pages::club-events.meetings.create')
            ->set('title', 'Sans propositions')
            ->set('dateMode', 'poll')
            ->set('dateProposals', [['proposed_at' => '']])
            ->call('save')
            ->assertHasErrors(['dateProposals']);

        expect(Meeting::count())->toBe(0);
    });

    test('end time defaults to two hours after start', function (): void {
        $start = now()->addWeek()->setTime(20, 0);

        Livewire::actingAs(createAdmin())
            ->test('pages::club-events.meetings.create')
            ->set('title', 'Durée par défaut')
            ->set('scheduledAt', $start->format('Y-m-d\TH:i'))
            ->call('save');

        $meeting = Meeting::where('title', 'Durée par défaut')->first();
        expect($meeting->ends_at->format('H:i'))->toBe('22:00');
    });
});
