<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\UserCalendarService;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Trainings\Models\Training;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const CANCELLED_CALENDAR_COMPONENT = 'pages::club-admin.users.user-space.calendar';

/**
 * Les séances remontées par le calendrier d'un membre, par id.
 *
 * @return array<int, array<string, mixed>>
 */
function calendarTrainingsOf(User $user, bool $showAllEvents = false): array
{
    return app(UserCalendarService::class)
        ->eventsFor($user, $showAllEvents, ['training'])
        ->keyBy('sourceId')
        ->all();
}

describe('séances annulées', function (): void {
    it('garde la séance annulée du pack où le membre est inscrit', function (): void {
        $season = makeActiveSeason();
        $pack = makeTrainingPack($season);

        $user = User::factory()->create();
        Subscription::factory()->for($user)->create([
            'season_id' => $season->id,
            'status' => 'confirmed',
        ])->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $cancelled = Training::factory()->cancelledClosed()->create([
            'training_pack_id' => $pack->id,
            'season_id' => $season->id,
            'cancellation_note' => 'Assemblée générale',
        ]);

        expect(calendarTrainingsOf($user))->toHaveKey($cancelled->id);
    });

    it('garde la séance annulée que le membre entraîne', function (): void {
        $season = makeActiveSeason();
        $coach = User::factory()->create();

        $cancelled = Training::factory()->cancelledFree()->create([
            'season_id' => $season->id,
            'trainer_id' => $coach->id,
        ]);

        expect(calendarTrainingsOf($coach))->toHaveKey($cancelled->id);
    });

    it('garde la séance annulée à laquelle le membre est rattaché nominativement', function (): void {
        $season = makeActiveSeason();
        $user = User::factory()->create();

        $cancelled = Training::factory()->cancelledClosed()->create(['season_id' => $season->id]);
        $cancelled->trainees()->attach($user->id);

        expect(calendarTrainingsOf($user))->toHaveKey($cancelled->id);
    });

    it('garde la séance annulée en mode « tous les événements du club »', function (): void {
        $season = makeActiveSeason();
        $user = User::factory()->create();

        $cancelled = Training::factory()->cancelledFree()->create(['season_id' => $season->id]);

        expect(calendarTrainingsOf($user, showAllEvents: true))->toHaveKey($cancelled->id);
    });

    it('distingue la salle laissée ouverte de la salle fermée', function (): void {
        $season = makeActiveSeason();
        $user = User::factory()->create();

        $free = Training::factory()->cancelledFree()->create([
            'season_id' => $season->id,
            'cancellation_note' => 'Coach absent',
        ]);
        $closed = Training::factory()->cancelledClosed()->create(['season_id' => $season->id]);
        $held = Training::factory()->create(['season_id' => $season->id]);

        $rows = calendarTrainingsOf($user, showAllEvents: true);

        expect($rows[$free->id]['isCancelled'])->toBeTrue()
            ->and($rows[$free->id]['roomStaysOpen'])->toBeTrue()
            ->and($rows[$free->id]['cancellationNote'])->toBe('Coach absent')
            ->and($rows[$closed->id]['isCancelled'])->toBeTrue()
            ->and($rows[$closed->id]['roomStaysOpen'])->toBeFalse()
            ->and($rows[$held->id]['isCancelled'])->toBeFalse()
            ->and($rows[$held->id]['roomStaysOpen'])->toBeFalse();
    });
});

describe('réunions annulées', function (): void {
    it('garde une assemblée générale annulée sur le calendrier', function (): void {
        makeActiveSeason();
        $user = User::factory()->create();

        $cancelled = Meeting::factory()->generalAssembly()->cancelled()->create([
            'cancellation_note' => 'Quorum non atteint',
        ]);

        $rows = app(UserCalendarService::class)
            ->eventsFor($user, true, ['meeting'])
            ->keyBy('sourceId');

        expect($rows)->toHaveKey($cancelled->id)
            ->and($rows[$cancelled->id]['isCancelled'])->toBeTrue()
            ->and($rows[$cancelled->id]['cancellationNote'])->toBe('Quorum non atteint');
    });

    it('laisse hors du calendrier les réunions qui ne sont ni confirmées ni annulées', function (): void {
        makeActiveSeason();
        $user = User::factory()->create();

        $planning = Meeting::factory()->generalAssembly()->planning()->create();

        $rows = app(UserCalendarService::class)
            ->eventsFor($user, true, ['meeting'])
            ->keyBy('sourceId');

        expect($rows)->not->toHaveKey($planning->id);
    });
});

describe('rendu du calendrier', function (): void {
    it('barre la séance annulée et en donne le motif dans le panneau du jour', function (): void {
        $this->travelTo(Carbon::parse('2026-07-15 10:00'));
        $season = makeActiveSeason();
        $user = User::factory()->create();

        $start = Carbon::parse('2026-07-20 18:00');
        $cancelled = Training::factory()->cancelledFree()->create([
            'season_id' => $season->id,
            'start' => $start,
            'end' => $start->copy()->addMinutes(90),
            'cancellation_note' => 'Coach en mariage',
        ]);

        $html = Livewire::actingAs($user)
            ->test(CANCELLED_CALENDAR_COMPONENT, ['user' => $user])
            ->set('showAllEvents', true)
            ->call('selectDay', '2026-07-20')
            ->html();

        // Tous les panneaux du mois sont rendus d'un coup : assertSee ne
        // localiserait rien. On découpe sur la clé du jour visé avant d'assener
        // la moindre assertion.
        $panel = str($html)->after('wire:key="panel-2026-07-20"')->before('wire:key="panel-2026-07-21"')->toString();

        expect($panel)->toContain('line-through')
            ->and($panel)->toContain('Salle ouverte en libre')
            ->and($panel)->toContain('Coach en mariage')
            // Proposer de s'inscrire à ce qui vient d'être annulé serait pire
            // que de le cacher.
            ->and($panel)->not->toContain(__('Register'));

        expect($cancelled->fresh()->status)->toBe('cancelled_free');
    });
});

describe('flux ICS', function (): void {
    it('marque la séance annulée au lieu de la retirer du flux', function (): void {
        $season = makeActiveSeason();
        $coach = User::factory()->create();

        Training::factory()->cancelledClosed()->create([
            'season_id' => $season->id,
            'trainer_id' => $coach->id,
            'start' => now()->addDays(3)->setTime(18, 0),
            'end' => now()->addDays(3)->setTime(19, 30),
        ]);

        $body = $this->get(URL::signedRoute('admin.user.calendar.ics', ['user' => $coach]))
            ->assertOk()
            ->getContent();

        expect($body)->toContain('CATEGORIES:TRAINING')
            ->and($body)->toContain('STATUS:CANCELLED');
    });

    it('ne marque pas annulée une séance qui tient toujours', function (): void {
        $season = makeActiveSeason();
        $coach = User::factory()->create();

        Training::factory()->create([
            'season_id' => $season->id,
            'trainer_id' => $coach->id,
            'start' => now()->addDays(3)->setTime(18, 0),
            'end' => now()->addDays(3)->setTime(19, 30),
        ]);

        $body = $this->get(URL::signedRoute('admin.user.calendar.ics', ['user' => $coach]))
            ->assertOk()
            ->getContent();

        expect($body)->toContain('CATEGORIES:TRAINING')
            ->and($body)->not->toContain('STATUS:CANCELLED');
    });
});
