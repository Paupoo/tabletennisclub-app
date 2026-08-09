<?php

declare(strict_types=1);

use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The suite already runs on the NOT NULL columns, so widen them back first:
 * that is the nullable shape this migration meets in production.
 */
function withNullablePackDates(callable $seed): Migration
{
    $migration = require base_path('database/migrations/2026_07_26_124350_require_pack_dates_on_training_packs.php');
    $migration->down();

    $seed();

    return $migration;
}

describe('training pack period backfill', function (): void {
    it('gives a dateless pack the period of its season and leaves a camp alone', function (): void {
        $season = makeActiveSeason();
        $ids = [];

        $migration = withNullablePackDates(function () use ($season, &$ids): void {
            $ids['dateless'] = makeTrainingPack($season)->id;
            $ids['camp'] = makeTrainingPack($season, [
                'pack_start_date' => '2027-07-05',
                'pack_end_date' => '2027-07-16',
            ])->id;

            DB::table('training_packs')
                ->where('id', $ids['dateless'])
                ->update(['pack_start_date' => null, 'pack_end_date' => null]);
        });

        $migration->up();

        $dateless = TrainingPack::find($ids['dateless']);
        $camp = TrainingPack::find($ids['camp']);

        expect($dateless->pack_start_date->toDateString())->toBe($season->start_at->toDateString())
            ->and($dateless->pack_end_date->toDateString())->toBe($season->end_at->toDateString())
            ->and($camp->pack_start_date->toDateString())->toBe('2027-07-05')
            ->and($camp->pack_end_date->toDateString())->toBe('2027-07-16');
    })->group('training', 'pack-period');

    it('fills only the side that is missing', function (): void {
        $season = makeActiveSeason();
        $id = null;

        $migration = withNullablePackDates(function () use ($season, &$id): void {
            $id = makeTrainingPack($season, ['pack_start_date' => '2027-07-05'])->id;

            DB::table('training_packs')->where('id', $id)->update(['pack_end_date' => null]);
        });

        $migration->up();

        $pack = TrainingPack::find($id);

        expect($pack->pack_start_date->toDateString())->toBe('2027-07-05')
            ->and($pack->pack_end_date->toDateString())->toBe($season->end_at->toDateString());
    })->group('training', 'pack-period');
});
