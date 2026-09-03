<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\TrainingPack;
use Livewire\Livewire;

it('still shows a closed pack to the member, flagged as closed', function (): void {
    $season = Season::factory()->create(['is_active' => true]);
    $subscription = Subscription::factory()->for($season, 'season')->create();

    $closed = TrainingPack::factory()->enrolmentsClosed()->for($season, 'season')->create([
        'max_participants' => 5,
        'price' => 90,
        'name' => 'Mardi Élite',
    ]);

    // Le faire disparaître ferait croire à un bug ou à une suppression. Il
    // reste affiché, avec « Inscriptions closes » à la place du bouton.
    $packs = Livewire::actingAs($subscription->user)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $subscription->user])
        ->viewData('availablePacks');

    $entry = collect($packs)->firstWhere('id', $closed->id);

    expect($entry)->not->toBeNull()
        ->and($entry['enrollments_open'])->toBeFalse();
})->group('training', 'enrollment');
