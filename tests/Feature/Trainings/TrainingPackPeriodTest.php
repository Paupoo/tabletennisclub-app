<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\TrainingLevel;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const TRAININGS_INDEX = 'pages::club-events.trainings.index';

/**
 * Fill in everything the pack wizard needs except the period under test.
 */
function fillPackWizard(User $admin, int $seasonId, int $roomId): Testable
{
    return Livewire::actingAs($admin)
        ->test(TRAININGS_INDEX)
        ->call('openCreate')
        ->set('formSeasonId', $seasonId)
        ->set('formName', 'Mardi — Perfectionnement')
        ->set('formLevel', TrainingLevel::INTERMEDIATE->value)
        ->set('formType', TrainingType::DIRECTED->value)
        ->set('formRoomId', $roomId)
        ->set('formTrainerId', User::factory()->create()->id)
        ->set('formDayOfWeek', 2)
        ->set('formStartTime', '18:00')
        ->set('formDurationMinutes', 90)
        ->set('formPrice', 90);
}

describe('a pack always declares the period it covers', function (): void {
    it('prefills the wizard with the season, so the usual pack needs no typing', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();

        Livewire::actingAs($admin)
            ->test(TRAININGS_INDEX)
            ->call('openCreate')
            ->assertSet('formPackStartDate', $season->start_at->toDateString())
            ->assertSet('formPackEndDate', $season->end_at->toDateString());
    })->group('training', 'pack-period');

    it('refuses to save a pack whose period is blank', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);

        fillPackWizard($admin, $season->id, $pack->room_id)
            ->set('formPackStartDate', '')
            ->set('formPackEndDate', '')
            ->call('save')
            ->assertHasErrors(['formPackStartDate', 'formPackEndDate']);
    })->group('training', 'pack-period');

    it('refuses a period that ends before it starts', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);

        fillPackWizard($admin, $season->id, $pack->room_id)
            ->set('formPackStartDate', '2027-01-10')
            ->set('formPackEndDate', '2026-12-01')
            ->call('save')
            ->assertHasErrors('formPackEndDate');
    })->group('training', 'pack-period');

    it('keeps the dates typed for a camp that runs outside the season', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);

        fillPackWizard($admin, $season->id, $pack->room_id)
            ->set('formName', "Stage d'été")
            ->set('formPackStartDate', '2027-07-05')
            ->set('formPackEndDate', '2027-07-16')
            ->call('save')
            ->assertHasNoErrors();

        $camp = TrainingPack::where('name', "Stage d'été")->sole();

        expect($camp->pack_start_date->toDateString())->toBe('2027-07-05')
            ->and($camp->pack_end_date->toDateString())->toBe('2027-07-16');
    })->group('training', 'pack-period');

    it('refuses the two dates at the database level too', function (): void {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);

        expect(fn () => DB::table('training_packs')
            ->where('id', $pack->id)
            ->update(['pack_start_date' => null]))
            ->toThrow(QueryException::class);
    })->group('training', 'pack-period');
});
