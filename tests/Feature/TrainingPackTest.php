<?php

declare(strict_types=1);

use App\Enums\TrainingLevel;
use App\Enums\TrainingType;
use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Training\TrainingPack;

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeActiveSeason(): Season
{
    return Season::factory()->create([
        'is_active' => true,
        'start_at' => now()->startOfYear(),
        'end_at' => now()->endOfYear(),
    ]);
}

function makeTrainingPack(Season $season, array $overrides = []): TrainingPack
{
    return TrainingPack::factory()->create(array_merge([
        'season_id' => $season->id,
        'level' => TrainingLevel::INTERMEDIATE->value,
        'type' => TrainingType::DIRECTED->value,
        'day_of_week' => 2,
        'start_time' => '18:00:00',
        'duration_minutes' => 90,
        'is_active' => true,
    ], $overrides));
}

// ── generateSessions ──────────────────────────────────────────────────────────

describe('generateSessions', function () {
    it('generates training sessions for the season', function () {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);

        $pack->generateSessions($season);

        expect($pack->trainings()->count())->toBeGreaterThan(0);
    });

    it('generates weekly sessions', function () {
        $season = Season::factory()->create([
            'is_active' => true,
            'start_at' => now()->startOfMonth(),
            'end_at' => now()->startOfMonth()->addDays(20),
        ]);
        $pack = makeTrainingPack($season, ['day_of_week' => 3]);

        $pack->generateSessions($season);

        $trainings = $pack->trainings()->orderBy('start')->get();

        if ($trainings->count() >= 2) {
            $diff = (int) $trainings[0]->start->diffInDays($trainings[1]->start);
            expect($diff)->toBe(7);
        }

        expect($trainings->count())->toBeGreaterThanOrEqual(1);
    });

    it('does not create duplicate sessions on the same date', function () {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);

        $pack->generateSessions($season);
        $firstCount = $pack->trainings()->count();

        $pack->generateSessions($season);
        expect($pack->trainings()->count())->toBe($firstCount);
    });

    it('creates sessions with correct start and end times', function () {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, [
            'start_time' => '19:00:00',
            'duration_minutes' => 90,
        ]);

        $pack->generateSessions($season);

        $session = $pack->trainings()->first();
        expect($session->start->format('H:i'))->toBe('19:00');
        expect($session->end->format('H:i'))->toBe('20:30');
    });

    it('returns without generating when day_of_week is null', function () {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['day_of_week' => null]);

        $pack->generateSessions($season);

        expect($pack->trainings()->count())->toBe(0);
    });

    it('links generated sessions to the correct pack', function () {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);

        $pack->generateSessions($season);

        $session = $pack->trainings()->first();
        expect($session->training_pack_id)->toBe($pack->id);
    });
});

// ── effectiveMaxParticipants ──────────────────────────────────────────────────

describe('effectiveMaxParticipants', function () {
    it('returns max_participants when explicitly set', function () {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['max_participants' => 12]);

        expect($pack->effectiveMaxParticipants())->toBe(12);
    });

    it('falls back to room capacity when max_participants is null', function () {
        $room = Room::factory()->create(['capacity_for_trainings' => 15]);
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, [
            'room_id' => $room->id,
            'max_participants' => null,
        ]);
        $pack->load('room');

        expect($pack->effectiveMaxParticipants())->toBe(15);
    });
});

// ── model bug fixes ───────────────────────────────────────────────────────────

describe('model bug fixes', function () {
    it('Room.trainingPacks() returns TrainingPack instances', function () {
        $room = Room::factory()->create();
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['room_id' => $room->id]);

        $result = $room->trainingPacks()->first();

        expect($result)->toBeInstanceOf(TrainingPack::class);
        expect($result->id)->toBe($pack->id);
    });

    it('User.trainings() pivot has status column', function () {
        $user = User::factory()->create();
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);
        $pack->generateSessions($season);
        $session = $pack->trainings()->first();

        $session->trainees()->attach($user->id, ['status' => 'present']);

        expect($user->trainings()->count())->toBe(1);
        expect($user->trainings()->first()->pivot->status)->toBe('present');
    });
});
