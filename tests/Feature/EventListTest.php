<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Registration;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Livewire\Public\Events\EventList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('EventList', function (): void {
    it('renders without error', function (): void {
        Livewire::test(EventList::class)->assertOk();
    });

    it('only shows published events', function (): void {
        Season::factory()->create(['is_active' => true, 'start_at' => now()->subYear(), 'end_at' => now()->addYear()]);

        EventPost::factory()->create([
            'title' => 'Published Event',
            'status' => EventPostStatusEnum::PUBLISHED,
            'event_date' => now()->addWeek(),
        ]);
        EventPost::factory()->create([
            'title' => 'Draft Event',
            'status' => EventPostStatusEnum::DRAFT,
            'event_date' => now()->addWeek(),
        ]);

        Livewire::test(EventList::class)
            ->assertSee('Published Event')
            ->assertDontSee('Draft Event');
    });

    it('defaults to active season and filters by event date range', function (): void {
        Season::factory()->create([
            'is_active' => true,
            'start_at' => now()->subMonths(6),
            'end_at' => now()->addMonths(6),
        ]);
        Season::factory()->create([
            'is_active' => false,
            'start_at' => now()->subYears(2),
            'end_at' => now()->subYears(2)->addMonths(10),
        ]);

        EventPost::factory()->create([
            'title' => 'Current Season Event',
            'status' => EventPostStatusEnum::PUBLISHED,
            'event_date' => now()->addWeek(),
        ]);
        EventPost::factory()->create([
            'title' => 'Old Season Event',
            'status' => EventPostStatusEnum::PUBLISHED,
            'event_date' => now()->subYears(2),
        ]);

        Livewire::test(EventList::class)
            ->assertSee('Current Season Event')
            ->assertDontSee('Old Season Event');
    });

    it('can filter by event type', function (): void {
        Season::factory()->create(['is_active' => true, 'start_at' => now()->subYear(), 'end_at' => now()->addYear()]);

        EventPost::factory()->create([
            'title' => 'A Tournament',
            'status' => EventPostStatusEnum::PUBLISHED,
            'type' => ClubEventTypeEnum::TOURNAMENT,
            'event_date' => now()->addWeek(),
        ]);
        EventPost::factory()->create([
            'title' => 'A Training',
            'status' => EventPostStatusEnum::PUBLISHED,
            'type' => ClubEventTypeEnum::TRAINING,
            'event_date' => now()->addWeek(),
        ]);

        Livewire::test(EventList::class)
            ->set('type', ClubEventTypeEnum::TOURNAMENT->value)
            ->assertSee('A Tournament')
            ->assertDontSee('A Training');
    });

    it('can clear all filters', function (): void {
        $activeSeason = Season::factory()->create(['is_active' => true, 'start_at' => now()->subYear(), 'end_at' => now()->addYear()]);

        Livewire::test(EventList::class)
            ->set('type', ClubEventTypeEnum::TOURNAMENT->value)
            ->call('clearAllFilters')
            ->assertSet('type', '')
            ->assertSet('seasonId', $activeSeason->id);
    });

    it('shows contact us button for anonymous visitors on upcoming events', function (): void {
        Season::factory()->create(['is_active' => true, 'start_at' => now()->subYear(), 'end_at' => now()->addYear()]);

        EventPost::factory()->create([
            'title' => 'Upcoming Event',
            'status' => EventPostStatusEnum::PUBLISHED,
            'event_date' => now()->addWeek(),
        ]);

        Livewire::test(EventList::class)
            ->assertSee(__('Contact us'));
    });

    it('shows register button for authenticated non-registered users on upcoming events', function (): void {
        Season::factory()->create(['is_active' => true, 'start_at' => now()->subYear(), 'end_at' => now()->addYear()]);
        $user = User::factory()->create();

        EventPost::factory()->create([
            'title' => 'Upcoming Event',
            'status' => EventPostStatusEnum::PUBLISHED,
            'event_date' => now()->addWeek(),
        ]);

        Livewire::actingAs($user)
            ->test(EventList::class)
            ->assertSee(__('Register'));
    });

    it('shows registered state for authenticated users already registered', function (): void {
        Season::factory()->create(['is_active' => true, 'start_at' => now()->subYear(), 'end_at' => now()->addYear()]);
        $user = User::factory()->create();

        $event = EventPost::factory()->create([
            'title' => 'Upcoming Event',
            'status' => EventPostStatusEnum::PUBLISHED,
            'event_date' => now()->addWeek(),
        ]);

        Registration::factory()->create([
            'user_id' => $user->id,
            'event_post_id' => $event->id,
        ]);

        Livewire::actingAs($user)
            ->test(EventList::class)
            ->assertSee(__('Registered'));
    });

    it('shows events dated in the gap between two seasons under the active season', function (): void {
        Season::factory()->create([
            'is_active' => false,
            'start_at' => now()->subYear(),
            'end_at' => now()->subMonths(8),
        ]);
        Season::factory()->create([
            'is_active' => true,
            'start_at' => now()->subMonths(6),
            'end_at' => now()->addMonths(6),
        ]);

        EventPost::factory()->create([
            'title' => 'Summer Break Event',
            'status' => EventPostStatusEnum::PUBLISHED,
            'event_date' => now()->subMonths(7),
        ]);

        Livewire::test(EventList::class)
            ->assertSee('Summer Break Event');
    });

    it('shows events dated after the active season ends under both the active and the next season', function (): void {
        Season::factory()->create([
            'is_active' => true,
            'start_at' => now()->subMonths(6),
            'end_at' => now()->addMonths(1),
        ]);
        $nextSeason = Season::factory()->create([
            'is_active' => false,
            'start_at' => now()->addMonths(3),
            'end_at' => now()->addMonths(15),
        ]);

        EventPost::factory()->create([
            'title' => 'Summer Camp',
            'status' => EventPostStatusEnum::PUBLISHED,
            'event_date' => now()->addMonths(2),
        ]);

        Livewire::test(EventList::class)
            ->assertSee('Summer Camp')
            ->set('seasonId', $nextSeason->id)
            ->assertSee('Summer Camp');
    });

    it('shows a break message with season navigation when the active season has no events', function (): void {
        Season::factory()->create([
            'is_active' => false,
            'start_at' => now()->subYears(2),
            'end_at' => now()->subYear(),
        ]);
        Season::factory()->create([
            'is_active' => true,
            'start_at' => now()->subMonths(6),
            'end_at' => now()->addMonths(6),
        ]);

        Livewire::test(EventList::class)
            ->assertSee(__("It's break time!"))
            ->assertSee(__('View last season'));
    });

    it('can switch season via viewSeason', function (): void {
        Season::factory()->create(['is_active' => true, 'start_at' => now()->subYear(), 'end_at' => now()->addYear()]);
        $otherSeason = Season::factory()->create(['is_active' => false, 'start_at' => now()->subYears(3), 'end_at' => now()->subYears(2)]);

        Livewire::test(EventList::class)
            ->call('viewSeason', $otherSeason->id)
            ->assertSet('seasonId', $otherSeason->id);
    });

    it('does not show register button for past events', function (): void {
        Season::factory()->create(['is_active' => true, 'start_at' => now()->subYear(), 'end_at' => now()->addYear()]);
        $user = User::factory()->create();

        EventPost::factory()->create([
            'title' => 'Past Event',
            'status' => EventPostStatusEnum::PUBLISHED,
            'event_date' => now()->subWeek(),
        ]);

        Livewire::actingAs($user)
            ->test(EventList::class)
            ->assertDontSee(__('Register'));
    });
});
