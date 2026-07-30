<?php

declare(strict_types=1);

use App\Domains\Trainings\Models\TrainingPack;

describe('scheduleLabel', function (): void {
    it('formats a weekly pack with day, time range', function (): void {
        $pack = new TrainingPack([
            'day_of_week' => 2,
            'start_time' => '20:30:00',
            'duration_minutes' => 90,
        ]);

        expect($pack->scheduleLabel())->toBe('Mardi · 20h30 – 22h00');
    });

    it('collapses contiguous multi-day packs into a range', function (): void {
        $pack = new TrainingPack([
            'days_of_week' => [1, 2, 3, 4, 5],
            'start_time' => '09:00:00',
            'duration_minutes' => 420,
        ]);

        expect($pack->scheduleLabel())->toBe('Du lundi au vendredi · 9h00 – 16h00');
    });

    it('lists non-contiguous days explicitly', function (): void {
        $pack = new TrainingPack([
            'days_of_week' => [1, 3],
            'start_time' => '18:00:00',
            'duration_minutes' => 120,
        ]);

        expect($pack->scheduleLabel())->toBe('Lundi & mercredi · 18h00 – 20h00');
    });

    it('appends custom date bounds when both are set', function (): void {
        $pack = new TrainingPack([
            'days_of_week' => [1, 2, 3, 4, 5],
            'start_time' => '09:00:00',
            'duration_minutes' => 420,
            'pack_start_date' => '2027-07-05',
            'pack_end_date' => '2027-07-16',
        ]);

        expect($pack->scheduleLabel())->toBe('Du lundi au vendredi · 9h00 – 16h00 · du 05/07 au 16/07');
    });

    it('shows only the start time when duration is missing', function (): void {
        $pack = new TrainingPack([
            'day_of_week' => 6,
            'start_time' => '09:00:00',
        ]);

        expect($pack->scheduleLabel())->toBe('Samedi · 9h00');
    });

    it('shows only the day when time is missing', function (): void {
        $pack = new TrainingPack([
            'day_of_week' => 1,
        ]);

        expect($pack->scheduleLabel())->toBe('Lundi');
    });

    it('returns null when no day nor time is set', function (): void {
        $pack = new TrainingPack;

        expect($pack->scheduleLabel())->toBeNull();
    });
});
