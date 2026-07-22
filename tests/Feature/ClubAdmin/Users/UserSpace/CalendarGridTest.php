<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const CALENDAR_GRID_COMPONENT = 'pages::club-admin.users.user-space.calendar';

it('opens on the current month with today selected', function (): void {
    $this->travelTo(Carbon::parse('2026-07-15 10:00'));
    makeActiveSeason();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CALENDAR_GRID_COMPONENT, ['user' => $user])
        ->assertSet('month', '2026-07')
        ->assertSet('selectedDay', '2026-07-15');
});

it('navigates between months and re-anchors the selected day', function (): void {
    $this->travelTo(Carbon::parse('2026-07-15 10:00'));
    makeActiveSeason();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CALENDAR_GRID_COMPONENT, ['user' => $user])
        ->call('nextMonth')
        ->assertSet('month', '2026-08')
        ->assertSet('selectedDay', '2026-08-01')
        ->call('previousMonth')
        ->assertSet('month', '2026-07')
        ->assertSet('selectedDay', '2026-07-15')
        ->call('previousMonth')
        ->assertSet('month', '2026-06')
        ->assertSet('selectedDay', '2026-06-01')
        ->call('goToToday')
        ->assertSet('month', '2026-07')
        ->assertSet('selectedDay', '2026-07-15');
});

it('shows past events of the displayed month', function (): void {
    $this->travelTo(Carbon::parse('2026-07-15 10:00'));
    makeActiveSeason();
    $user = User::factory()->create();

    $meeting = Meeting::factory()->confirmed()->create([
        'scheduled_at' => Carbon::parse('2026-07-05 20:00'),
        'ends_at' => Carbon::parse('2026-07-05 22:00'),
    ]);

    $component = Livewire::actingAs($user)
        ->test(CALENDAR_GRID_COMPONENT, ['user' => $user])
        ->set('showAllEvents', true);

    expect($component->instance()->eventsByDay)->toHaveKey('2026-07-05');
    $component->assertSee($meeting->title);
});

it('repeats a multi-day tournament on every covered day', function (): void {
    $this->travelTo(Carbon::parse('2026-07-15 10:00'));
    makeActiveSeason();
    $user = User::factory()->create();

    Tournament::factory()->create([
        'status' => TournamentStatusEnum::PUBLISHED,
        'start_date' => Carbon::parse('2026-07-18 09:00'),
        'end_date' => Carbon::parse('2026-07-19 18:00'),
    ]);

    $component = Livewire::actingAs($user)
        ->test(CALENDAR_GRID_COMPONENT, ['user' => $user])
        ->set('showAllEvents', true);

    $eventsByDay = $component->instance()->eventsByDay;

    expect($eventsByDay)->toHaveKeys(['2026-07-18', '2026-07-19'])
        ->and($eventsByDay['2026-07-18'][0]['type'])->toBe('tournament')
        ->and($eventsByDay['2026-07-19'][0]['type'])->toBe('tournament');
});

it('lists the events of the selected day in the side panel and ignores invalid dates', function (): void {
    $this->travelTo(Carbon::parse('2026-07-15 10:00'));
    makeActiveSeason();
    $user = User::factory()->create();

    Meeting::factory()->confirmed()->create([
        'scheduled_at' => Carbon::parse('2026-07-09 20:00'),
        'ends_at' => Carbon::parse('2026-07-09 22:00'),
    ]);

    $component = Livewire::actingAs($user)
        ->test(CALENDAR_GRID_COMPONENT, ['user' => $user])
        ->set('showAllEvents', true);

    expect($component->instance()->selectedDayEvents)->toBeEmpty();

    $component->call('selectDay', '2026-07-09')
        ->assertSet('selectedDay', '2026-07-09');

    expect($component->instance()->selectedDayEvents)->toHaveCount(1);

    $component->call('selectDay', 'not-a-date')
        ->assertSet('selectedDay', '2026-07-09');
});

it('jumps to any month from the picker and rejects invalid input', function (): void {
    $this->travelTo(Carbon::parse('2026-07-15 10:00'));
    makeActiveSeason();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CALENDAR_GRID_COMPONENT, ['user' => $user])
        ->call('setMonth', '2027-03')
        ->assertSet('month', '2027-03')
        ->assertSet('selectedDay', '2027-03-01')
        ->call('setMonth', 'garbage')
        ->assertSet('month', '2027-03');
});

it('toggles categories from the legend and ignores unknown ones', function (): void {
    $this->travelTo(Carbon::parse('2026-07-15 10:00'));
    makeActiveSeason();
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(CALENDAR_GRID_COMPONENT, ['user' => $user])
        ->call('toggleCategory', 'training');

    expect($component->get('selectedCategories'))->toBe(['training']);

    $component->call('toggleCategory', 'training');

    expect($component->get('selectedCategories'))->toBe([]);

    $component->call('toggleCategory', 'bogus');

    expect($component->get('selectedCategories'))->toBe([]);
});

it('tags continuation days of a multi-day tournament with their position', function (): void {
    $this->travelTo(Carbon::parse('2026-07-15 10:00'));
    makeActiveSeason();
    $user = User::factory()->create();

    Tournament::factory()->create([
        'status' => TournamentStatusEnum::PUBLISHED,
        'start_date' => Carbon::parse('2026-07-18 09:00'),
        'end_date' => Carbon::parse('2026-07-19 18:00'),
    ]);

    $component = Livewire::actingAs($user)
        ->test(CALENDAR_GRID_COMPONENT, ['user' => $user])
        ->set('showAllEvents', true);

    $eventsByDay = $component->instance()->eventsByDay;

    expect($eventsByDay['2026-07-18'][0])->toMatchArray(['dayIndex' => 1, 'dayCount' => 2])
        ->and($eventsByDay['2026-07-19'][0])->toMatchArray(['dayIndex' => 2, 'dayCount' => 2]);
});

it('initialises month and selected day from the query string', function (): void {
    $this->travelTo(Carbon::parse('2026-07-15 10:00'));
    makeActiveSeason();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->withQueryParams(['month' => '2026-09', 'selectedDay' => '2026-09-12'])
        ->test(CALENDAR_GRID_COMPONENT, ['user' => $user])
        ->assertSet('month', '2026-09')
        ->assertSet('selectedDay', '2026-09-12');
});

it('shows an empty-month hint with a shortcut to all club events', function (): void {
    $this->travelTo(Carbon::parse('2026-07-15 10:00'));
    makeActiveSeason();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CALENDAR_GRID_COMPONENT, ['user' => $user])
        ->assertSee(__('No events this month.'))
        ->assertSee(__('All club events'));
});

it('applies category filters to the grid', function (): void {
    $this->travelTo(Carbon::parse('2026-07-15 10:00'));
    makeActiveSeason();
    $user = User::factory()->create();

    Meeting::factory()->confirmed()->create([
        'scheduled_at' => Carbon::parse('2026-07-20 20:00'),
        'ends_at' => Carbon::parse('2026-07-20 22:00'),
    ]);

    $component = Livewire::actingAs($user)
        ->test(CALENDAR_GRID_COMPONENT, ['user' => $user])
        ->set('showAllEvents', true)
        ->set('selectedCategories', ['tournament']);

    expect($component->instance()->eventsByDay)->not->toHaveKey('2026-07-20');
});
