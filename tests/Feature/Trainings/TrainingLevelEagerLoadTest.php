<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
 * Le niveau d'un pack est devenu une relation (#93) : `level?->label` déclenche
 * donc une requête, et hors production le chargement paresseux est interdit.
 * Les quatre écrans qui lisent le niveau chargeaient `trainingPack` sans son
 * niveau — le calendrier tombait en LazyLoadingViolationException dès qu'une
 * séance rattachée à un pack entrait dans le mois affiché.
 *
 * Deux packs, pas un : Laravel n'arme la garde que si la requête a ramené plus
 * d'une ligne (Builder::hydrate). Une fixture à un seul pack laisse le
 * chargement paresseux passer sans bruit et rend le test complaisant.
 *
 * La suite ne l'avait pas vu non plus parce que les séances de
 * `Training::factory()` n'ont pas de pack : `trainingPack?->level` s'arrête
 * alors sur le premier `?->`.
 */

/** @return array{0: TrainingPack, 1: TrainingPack} */
function twoPacksWithLevels(mixed $season, User $coach): array
{
    return [
        makeTrainingPack($season, [
            'training_level_id' => trainingLevelId('Confirmé'),
            'trainer_id' => $coach->id,
        ]),
        makeTrainingPack($season, [
            'training_level_id' => trainingLevelId('Débutant'),
            'trainer_id' => $coach->id,
        ]),
    ];
}

beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->coach = User::factory()->isCoach()->create();

    [$this->packA, $this->packB] = twoPacksWithLevels($this->season, $this->coach);

    // Ancrées au milieu du mois affiché : la grille du calendrier couvre les
    // semaines entières du mois courant, le 15 y tombe toujours.
    $day = now()->startOfMonth()->addDays(14);

    foreach ([$this->packA, $this->packB] as $index => $pack) {
        Training::factory()
            ->for($pack, 'trainingPack')
            ->for($this->coach, 'trainer')
            ->create([
                'start' => $day->copy()->setTime(18 + $index, 0),
                'end' => $day->copy()->setTime(19 + $index, 30),
                'status' => 'scheduled',
            ]);
    }
});

it('reads the pack levels in the calendar without a lazy load', function (): void {
    $component = Livewire::actingAs($this->coach)
        ->test('pages::club-admin.users.user-space.calendar', ['user' => $this->coach]);

    $levels = collect($component->instance()->eventsByDay)->flatten(1)->pluck('level');

    expect($levels)->toContain('Confirmé')->toContain('Débutant');
})->group('training', 'calendar');

it('reads the pack levels in the whole-club calendar without a lazy load', function (): void {
    $stranger = User::factory()->create();

    $component = Livewire::actingAs($stranger)
        ->test('pages::club-admin.users.user-space.calendar', ['user' => $stranger])
        ->set('showAllEvents', true);

    $levels = collect($component->instance()->eventsByDay)->flatten(1)->pluck('level');

    expect($levels)->toContain('Confirmé')->toContain('Débutant');
})->group('training', 'calendar');

it('reads the pack levels on the member event page without a lazy load', function (): void {
    $member = User::factory()->create();
    $subscription = Subscription::factory()->for($member)->create([
        'season_id' => $this->season->id,
        'status' => 'confirmed',
    ]);
    $subscription->trainingPacks()->attach([
        $this->packA->id => ['status' => 'enrolled'],
        $this->packB->id => ['status' => 'enrolled'],
    ]);

    Training::where('training_pack_id', $this->packA->id)->update([
        'start' => now()->addDays(3)->setTime(18, 0),
        'end' => now()->addDays(3)->setTime(19, 30),
    ]);
    Training::where('training_pack_id', $this->packB->id)->update([
        'start' => now()->addDays(4)->setTime(18, 0),
        'end' => now()->addDays(4)->setTime(19, 30),
    ]);

    Livewire::actingAs($member)
        ->test('pages::club-admin.users.user-space.event-subscription', ['user' => $member])
        ->assertOk()
        ->assertSee('Confirmé')
        ->assertSee('Débutant');
})->group('training');

it('reads the pack levels on the coach screen without a lazy load', function (): void {
    Livewire::actingAs($this->coach)
        ->test('pages::club-events.trainings.coach')
        ->assertOk()
        ->assertSee('Confirmé')
        ->assertSee('Débutant');
})->group('training', 'attendance');
