<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use Livewire\Livewire;

// ── generateSessions ──────────────────────────────────────────────────────────

describe('generateSessions', function (): void {
    it('generates training sessions for the season', function (): void {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);

        $pack->generateSessions($season);

        expect($pack->trainings()->count())->toBeGreaterThan(0);
    });

    it('generates weekly sessions', function (): void {
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

    it('does not create duplicate sessions on the same date', function (): void {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);

        $pack->generateSessions($season);
        $firstCount = $pack->trainings()->count();

        $pack->generateSessions($season);
        expect($pack->trainings()->count())->toBe($firstCount);
    });

    it('creates sessions with correct start and end times', function (): void {
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

    it('returns without generating when day_of_week is null', function (): void {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['day_of_week' => null]);

        $pack->generateSessions($season);

        expect($pack->trainings()->count())->toBe(0);
    });

    it('generates sessions on multiple specific days', function (): void {
        $season = Season::factory()->create([
            'is_active' => true,
            'start_at' => now()->startOfMonth(),
            'end_at' => now()->startOfMonth()->addDays(13),
        ]);
        $pack = makeTrainingPack($season, [
            'day_of_week' => null,
            'days_of_week' => [1, 3], // Monday and Wednesday
        ]);

        $pack->generateSessions($season);

        // Expect sessions on both Mon and Wed within the 2-week window
        expect($pack->trainings()->count())->toBeGreaterThanOrEqual(2);
    });

    it('skips excluded dates when generating', function (): void {
        $season = Season::factory()->create([
            'is_active' => true,
            'start_at' => now()->startOfMonth(),
            'end_at' => now()->startOfMonth()->addDays(27),
        ]);

        // Find first Monday of the month
        $firstMonday = now()->startOfMonth()->startOfDay();
        $firstMonday->addDays((1 - $firstMonday->isoWeekday() + 7) % 7);
        $excluded = $firstMonday->toDateString();

        $pack = makeTrainingPack($season, [
            'day_of_week' => 1, // Monday
            'excluded_dates' => [$excluded],
        ]);

        $pack->generateSessions($season);

        $dates = $pack->trainings()->pluck('start')->map(fn ($d) => $d->toDateString());
        expect($dates)->not->toContain($excluded);
    });

    it('respects custom pack_start_date and pack_end_date', function (): void {
        $season = makeActiveSeason();
        $customStart = now()->startOfMonth()->addDays(10)->toDateString();
        $customEnd = now()->startOfMonth()->addDays(17)->toDateString();

        $pack = makeTrainingPack($season, [
            'day_of_week' => 2, // Tuesday
            'pack_start_date' => $customStart,
            'pack_end_date' => $customEnd,
        ]);

        $pack->generateSessions($season);

        $pack->trainings()->each(function ($t) use ($customStart, $customEnd): void {
            expect($t->start->toDateString())->toBeGreaterThanOrEqual($customStart);
            expect($t->start->toDateString())->toBeLessThanOrEqual($customEnd);
        });
    });

    it('links generated sessions to the correct pack', function (): void {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);

        $pack->generateSessions($season);

        $session = $pack->trainings()->first();
        expect($session->training_pack_id)->toBe($pack->id);
    });
});

// ── effectiveMaxParticipants ──────────────────────────────────────────────────

describe('effectiveMaxParticipants', function (): void {
    it('returns max_participants when explicitly set', function (): void {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['max_participants' => 12]);

        expect($pack->effectiveMaxParticipants())->toBe(12);
    });

    it('falls back to room capacity when max_participants is null', function (): void {
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

describe('model bug fixes', function (): void {
    it('Room.trainingPacks() returns TrainingPack instances', function (): void {
        $room = Room::factory()->create();
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['room_id' => $room->id]);

        $result = $room->trainingPacks()->first();

        expect($result)->toBeInstanceOf(TrainingPack::class);
        expect($result->id)->toBe($pack->id);
    });

    it('User.trainings() pivot has status column', function (): void {
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

// ── regression: trainer change cascades to sessions ───────────────────────────

describe('trainer update', function (): void {
    it('propagates trainer change to all linked sessions', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $coachA = User::factory()->create(['is_coach' => true]);
        $coachB = User::factory()->create(['is_coach' => true]);

        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['trainer_id' => $coachA->id]);
        $pack->generateSessions($season);

        expect(Training::where('training_pack_id', $pack->id)->pluck('trainer_id')->unique()->sole())
            ->toBe($coachA->id);

        Livewire::actingAs($admin)
            ->test('pages::club-events.trainings.index')
            ->call('openEdit', $pack->id)
            ->set('formTrainerId', $coachB->id)
            ->call('save');

        expect(Training::where('training_pack_id', $pack->id)->pluck('trainer_id')->unique()->sole())
            ->toBe($coachB->id);
    });
});

// ── regression: trainerOptions lists every coach (is_active column was dropped) ─

describe('trainerOptions', function (): void {
    it('lists every coach even without an active subscription, and excludes non-coaches', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();

        // Coach without any subscription — must still be selectable as a trainer.
        User::factory()->create([
            'is_coach' => true,
            'first_name' => 'Zelda',
            'last_name' => 'Coachowski',
        ]);

        // Active member (confirmed subscription) but not a coach — must not appear.
        activeMember($season, [
            'is_coach' => false,
            'first_name' => 'Nestor',
            'last_name' => 'Nocoach',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.trainings.index')
            ->call('openCreate')
            ->assertSee('Zelda Coachowski')
            ->assertDontSee('Nestor Nocoach');
    });
});
