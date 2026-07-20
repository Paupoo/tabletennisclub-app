<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\TrainingLevel;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\TrainingPack;

/**
 * The pack actions live in a dropdown at the bottom of a card in a four-column
 * grid. Two things broke there before and neither shows up in a Livewire test:
 * a labelled trigger burst out of the narrow card, and the card's
 * overflow-hidden clipped the open menu out of sight.
 */
it('opens the pack actions menu without clipping it', function (): void {
    $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $season = makeActiveSeason();
    $room = Room::factory()->create(['name' => 'Demeester -1', 'capacity_for_trainings' => 8]);
    $coach = User::factory()->create(['is_coach' => true, 'first_name' => 'Eric', 'last_name' => 'Filee']);

    $packs = [];

    foreach (['Mercredi — Initiation jeunes', 'Samedi — Initiation jeunes', 'Mardi — Perfectionnement'] as $i => $name) {
        $packs[] = TrainingPack::factory()->create([
            'name' => $name,
            'season_id' => $season->id,
            'room_id' => $room->id,
            'trainer_id' => $coach->id,
            'level' => TrainingLevel::BEGINNERS->value,
            'type' => TrainingType::DIRECTED->value,
            'day_of_week' => $i + 1,
            'start_time' => '15:00:00',
            'duration_minutes' => 90,
            'price' => 90,
            'is_active' => true,
        ]);
    }

    $this->actingAs($admin);

    visit(route('admin.trainings.index'))
        ->assertNoJavaScriptErrors()
        // Each card must carry its own trigger: mary derives the dropdown's
        // wire:key from the component, so identical triggers used to collide.
        ->click('[wire\\:key$="pack-actions-trigger-' . $packs[0]->id . '"]')
        ->assertSee('Retirer de l\'offre')
        ->assertSee('Arrêter le pack');
})->group('training');
