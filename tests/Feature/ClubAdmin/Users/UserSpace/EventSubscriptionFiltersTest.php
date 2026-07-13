<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const EVENT_SUB_COMPONENT = 'pages::club-admin.users.user-space.event-subscription';

it('filters sections by event type from the drawer', function (): void {
    makeActiveSeason();
    $user = User::factory()->create();
    Tournament::factory()->create([
        'status' => TournamentStatusEnum::PUBLISHED,
        'start_date' => now()->addDays(10),
    ]);

    Livewire::actingAs($user)
        ->test(EVENT_SUB_COMPONENT, ['user' => $user])
        ->assertSee(__('Upcoming Tournaments'))
        ->set('eventType', 'meeting')
        ->assertDontSee(__('Upcoming Tournaments'));
});

it('exposes active filters as removable chips', function (): void {
    makeActiveSeason();
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(EVENT_SUB_COMPONENT, ['user' => $user])
        ->set('eventType', 'tournament')
        ->set('onlyPayable', true);

    expect($component->instance()->getFilterChips())->toHaveCount(2);

    $component->call('removeFilter', 'onlyPayable');
    expect($component->get('onlyPayable'))->toBeFalse();

    $component->call('clearFilters');
    expect($component->get('eventType'))->toBe('');
});

it('keeps only payable tournaments when the filter is on', function (): void {
    makeActiveSeason();
    $user = User::factory()->create();
    Tournament::factory()->create([
        'status' => TournamentStatusEnum::PUBLISHED,
        'start_date' => now()->addDays(10),
        'name' => 'Tournoi sans dette',
    ]);

    Livewire::actingAs($user)
        ->test(EVENT_SUB_COMPONENT, ['user' => $user])
        ->set('onlyPayable', true)
        ->assertDontSee('Tournoi sans dette');
});

it('renders meetings with the payable filter on without erroring', function (): void {
    // Regression: upcomingMeetings and meetingRegistrations used to reference
    // each other, causing an undefined-property crash under the "to pay only"
    // filter.
    makeActiveSeason();
    $user = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);

    $payable = Meeting::factory()->confirmed()->withMeal('Pizzas', 1200)->create([
        'created_by' => $admin->id,
        'title' => 'Réunion avec repas',
        'scheduled_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addHours(2),
    ]);
    $payable->users()->attach($user->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value, 'meal_reserved' => true]);
    $registration = $payable->users()->where('users.id', $user->id)->first()->registration;
    $registration->payment()->create([
        'reference' => '001/2026/00009',
        'amount_due' => 12,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $free = Meeting::factory()->confirmed()->create([
        'created_by' => $admin->id,
        'title' => 'Réunion sans repas',
        'scheduled_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addHours(2),
    ]);
    $free->users()->attach($user->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value]);

    Livewire::actingAs($user)
        ->test(EVENT_SUB_COMPONENT, ['user' => $user])
        ->set('onlyPayable', true)
        ->assertOk()
        ->assertSee('Réunion avec repas')
        ->assertDontSee('Réunion sans repas');
});
