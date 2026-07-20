<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\TrainingLevel;
use App\Domains\Shared\Enums\TrainingType;
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

// ── price ─────────────────────────────────────────────────────────────────────

describe('price', function (): void {
    it('keeps the cents of a price entered with decimals', function (): void {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['price' => 87.50]);

        expect($pack->fresh()->price)->toBe(87.50);
    });

    it('stores the price as cents', function (): void {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['price' => 87.50]);

        expect((int) $pack->getRawOriginal('price'))->toBe(8750);
    });

    it('rounds a price with sub-cent precision to the nearest cent', function (): void {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['price' => 12.345]);

        expect($pack->fresh()->price)->toBe(12.35);
    });

    it('round-trips a whole-euro price unchanged', function (): void {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season, ['price' => 90]);

        expect($pack->fresh()->price)->toBe(90.0);
    });
});

// ── capacity ──────────────────────────────────────────────────────────────────

describe('capacity', function (): void {
    it('saves an explicit maximum from the wizard', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $room = Room::factory()->create(['capacity_for_trainings' => 16]);
        $coach = User::factory()->create(['is_coach' => true]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.trainings.index')
            ->call('openCreate')
            ->set('formSeasonId', $season->id)
            ->set('formName', 'Jeunes mardi')
            ->set('formLevel', TrainingLevel::KIDS->value)
            ->set('formType', TrainingType::DIRECTED->value)
            ->set('formRoomId', $room->id)
            ->set('formTrainerId', $coach->id)
            ->set('formDayOfWeek', 2)
            ->set('formMaxParticipants', '12')
            ->call('save');

        expect(TrainingPack::where('name', 'Jeunes mardi')->sole()->max_participants)->toBe(12);
    });

    it('falls back to the room capacity when the maximum is left empty', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $room = Room::factory()->create(['capacity_for_trainings' => 16]);
        $coach = User::factory()->create(['is_coach' => true]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.trainings.index')
            ->call('openCreate')
            ->set('formSeasonId', $season->id)
            ->set('formName', 'Sans plafond explicite')
            ->set('formLevel', TrainingLevel::KIDS->value)
            ->set('formType', TrainingType::DIRECTED->value)
            ->set('formRoomId', $room->id)
            ->set('formTrainerId', $coach->id)
            ->set('formDayOfWeek', 2)
            ->call('save');

        $pack = TrainingPack::where('name', 'Sans plafond explicite')->sole();

        expect($pack->max_participants)->toBeNull()
            ->and($pack->effectiveMaxParticipants())->toBe(16);
    });

    it('refuses unlimited enrolment on a directed pack', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $room = Room::factory()->create(['capacity_for_trainings' => 16]);
        $coach = User::factory()->create(['is_coach' => true]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.trainings.index')
            ->call('openCreate')
            ->set('formSeasonId', $season->id)
            ->set('formName', 'Dirige sans limite')
            ->set('formLevel', TrainingLevel::KIDS->value)
            ->set('formType', TrainingType::DIRECTED->value)
            ->set('formRoomId', $room->id)
            ->set('formTrainerId', $coach->id)
            ->set('formDayOfWeek', 2)
            ->set('formIsOpenEnrollment', true)
            ->call('save');

        $pack = TrainingPack::where('name', 'Dirige sans limite')->sole();

        expect($pack->is_open_enrollment)->toBeFalse()
            ->and($pack->hasAvailableSpot())->toBeTrue();
    });

    it('allows unlimited enrolment on a free-practice pack', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();
        $room = Room::factory()->create(['capacity_for_trainings' => 4]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.trainings.index')
            ->call('openCreate')
            ->set('formSeasonId', $season->id)
            ->set('formName', 'Libre du vendredi')
            ->set('formLevel', TrainingLevel::OPEN->value)
            ->set('formType', TrainingType::FREE->value)
            ->set('formRoomId', $room->id)
            ->set('formDayOfWeek', 5)
            ->set('formIsOpenEnrollment', true)
            ->call('save');

        expect(TrainingPack::where('name', 'Libre du vendredi')->sole()->is_open_enrollment)->toBeTrue();
    });
});

// ── Capacité et statut de l'inscription club ─────────────────────────────────

/**
 * Régression #29 : les comptages filtraient le statut du pivot mais jamais
 * celui de l'inscription club. Une inscription annulée gardait sa place et
 * pouvait faire afficher « complet » à un pack qui ne l'était pas.
 */
describe('capacity ignores terminated subscriptions', function (): void {
    beforeEach(function (): void {
        $this->season = makeActiveSeason();
        $this->pack = makeTrainingPack($this->season, ['max_participants' => 2]);
    });

    it('frees the slot of a subscription in a terminal state', function (string $status): void {
        $gone = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => $status,
        ]);
        $gone->trainingPacks()->attach($this->pack->id, ['status' => 'enrolled']);

        expect($this->pack->committedCount())->toBe(0);
        expect($this->pack->enrolledCount())->toBe(0);
    })->with(['cancelled', 'refunded']);

    it('keeps the slot of a member still awaiting validation', function (): void {
        $pendingMember = Subscription::factory()->pending()->create([
            'season_id' => $this->season->id,
        ]);
        $pendingMember->trainingPacks()->attach($this->pack->id, ['status' => 'enrolled']);

        expect($this->pack->committedCount())->toBe(1);
    });

    it('counts a confirmed member', function (): void {
        $member = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'confirmed',
        ]);
        $member->trainingPacks()->attach($this->pack->id, ['status' => 'enrolled']);

        expect($this->pack->committedCount())->toBe(1);
        expect($this->pack->enrolledCount())->toBe(1);
    });

    it('excludes cancelled members from the waitlist count', function (): void {
        $gone = Subscription::factory()->cancelled()->create(['season_id' => $this->season->id]);
        $gone->trainingPacks()->attach($this->pack->id, ['status' => 'waiting']);

        expect($this->pack->waitlistCount())->toBe(0);
    });
});
