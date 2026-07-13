<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
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
