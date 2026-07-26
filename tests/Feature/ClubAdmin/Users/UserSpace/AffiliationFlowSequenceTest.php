<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('sequences the affiliation flow: formula, then trainings, then summary and submit', function (): void {
    Season::factory()->create([
        'is_active' => true,
        'affiliations_open' => true,
        'start_at' => now()->startOfYear(),
        'end_at' => now()->endOfYear(),
    ]);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
        ->assertSeeInOrder([
            __('Competition'),
            __('Directed training'),
            __('Summary and submit'),
            __('Submit my affiliation'),
        ]);
});
