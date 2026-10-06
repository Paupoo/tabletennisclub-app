<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;

/*
 * Closing the enrolments of a pack was told nowhere on the list: only the
 * label of its « ⋮ » menu gave it away. The card now says it, for a pack still
 * on offer — a withdrawn one already says more with its own badge.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
    $this->season = makeActiveSeason();
});

it('marks a pack whose enrolments are closed', function (): void {
    makeTrainingPack($this->season, ['name' => 'Jeunes du mercredi', 'enrollments_open' => false]);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.trainings.index')
        ->assertSeeInOrder(['Jeunes du mercredi', __('Closed to enrolments')]);
});

it('says nothing while the enrolments are open', function (): void {
    makeTrainingPack($this->season, ['enrollments_open' => true]);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.trainings.index')
        ->assertDontSee(__('Closed to enrolments'));
});

it('leaves it to the withdrawn badge on a pack taken off the offer', function (): void {
    makeTrainingPack($this->season, ['enrollments_open' => false, 'is_active' => false]);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.trainings.index')
        ->set('showInactive', true)
        ->assertSee(__('Withdrawn'))
        ->assertDontSee(__('Closed to enrolments'));
});
