<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const CALENDAR_COMPONENT = 'pages::club-admin.users.user-space.calendar';

it('shows a chip per selected category and removes it individually', function (): void {
    makeActiveSeason();
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(CALENDAR_COMPONENT, ['user' => $user])
        ->set('selectedCategories', ['tournament', 'meeting'])
        ->assertSee(__('Tournament'))
        ->assertSee(__('Meeting'));

    $component->call('removeFilter', 'category:tournament');

    expect($component->get('selectedCategories'))->toBe(['meeting']);

    $component->call('clearFilters');

    expect($component->get('selectedCategories'))->toBe([]);
});

it('keeps the view mode segmented control out of the filter chips', function (): void {
    makeActiveSeason();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CALENDAR_COMPONENT, ['user' => $user])
        ->set('showAllEvents', true)
        ->assertSee(__('All club events'));

    // le mode de vue ne produit aucun chip de filtre
    $component = Livewire::actingAs($user)->test(CALENDAR_COMPONENT, ['user' => $user]);
    $component->set('showAllEvents', true);
    expect($component->instance()->getFilterChips())->toBe([]);
});
