<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Models\AppSetting;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Services\PublicAgendaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Le seam public de l'agenda : ce que la page d'accueil a le droit de montrer,
 * et sous quelle forme. Rien ici ne connaît Blade ni le HTML — la mise en page
 * est vérifiée par {@see tests/Feature/Homepage/HomepageAgendaTest.php}.
 */
beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-09-04 10:00:00');
    $this->season = Season::factory()->create([
        'is_active' => true,
        'start_at' => '2026-09-01',
        'end_at' => '2027-06-30',
    ]);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('liste une séance planifiée dans les quatorze prochains jours', function (): void {
    $room = Room::factory()->create(['name' => 'Demeester -1']);

    Training::factory()->create([
        'season_id' => $this->season->id,
        'room_id' => $room->id,
        'start' => '2026-09-07 18:00:00',
        'end' => '2026-09-07 20:00:00',
    ]);

    $agenda = app(PublicAgendaService::class)->forHomepage();

    expect($agenda->days)->toHaveCount(1);

    $day = $agenda->days[0];
    expect($day->date->toDateString())->toBe('2026-09-07')
        ->and($day->entries)->toHaveCount(1)
        ->and($day->entries[0]->startsAt->format('H:i'))->toBe('18:00')
        ->and($day->entries[0]->endsAt?->format('H:i'))->toBe('20:00')
        ->and($day->entries[0]->location)->toBe('Demeester -1');
});

it('conserve une séance annulée et expose de quel type d’annulation il s’agit', function (): void {
    $room = Room::factory()->create(['name' => 'Demeester -1']);

    Training::factory()->create([
        'season_id' => $this->season->id,
        'room_id' => $room->id,
        'start' => '2026-09-15 20:30:00',
        'end' => '2026-09-15 22:00:00',
        'status' => 'cancelled_closed',
        'cancellation_note' => 'Assemblée générale — salle fermée',
    ]);

    $agenda = app(PublicAgendaService::class)->forHomepage();

    $entry = $agenda->days[0]->entries[0];

    expect($entry->isCancelled())->toBeTrue()
        ->and($entry->roomStaysOpen())->toBeFalse()
        ->and($entry->cancellationNote)->toBe('Assemblée générale — salle fermée');
});

it('distingue une annulation qui laisse la salle ouverte en jeu libre', function (): void {
    Training::factory()->cancelledFree()->create([
        'season_id' => $this->season->id,
        'start' => '2026-09-14 18:00:00',
        'end' => '2026-09-14 20:00:00',
        'cancellation_note' => 'Coach absent',
    ]);

    $entry = app(PublicAgendaService::class)->forHomepage()->days[0]->entries[0];

    expect($entry->isCancelled())->toBeTrue()
        ->and($entry->roomStaysOpen())->toBeTrue();
});

it('montre les interclubs à domicile et laisse les déplacements de côté', function (): void {
    $ourClub = Club::factory()->ownClub()->create(['name' => 'CTT Ottignies-Blocry']);
    $rival = Club::factory()->create(['name' => 'TT Adversaire']);

    $ourTeam = Team::factory()->create(['club_id' => $ourClub->id, 'season_id' => $this->season->id, 'name' => 'A']);
    $theirTeam = Team::factory()->create(['club_id' => $rival->id, 'season_id' => $this->season->id, 'name' => 'C']);

    Interclub::factory()->create([
        'season_id' => $this->season->id,
        'visited_team_id' => $ourTeam->id,
        'visiting_team_id' => $theirTeam->id,
        'start_date_time' => '2026-09-11 20:00:00',
        'is_bye' => false,
    ]);

    Interclub::factory()->create([
        'season_id' => $this->season->id,
        'visited_team_id' => $theirTeam->id,
        'visiting_team_id' => $ourTeam->id,
        'start_date_time' => '2026-09-12 20:00:00',
        'is_bye' => false,
    ]);

    $agenda = app(PublicAgendaService::class)->forHomepage();

    expect($agenda->days)->toHaveCount(1)
        ->and($agenda->days[0]->date->toDateString())->toBe('2026-09-11')
        ->and($agenda->days[0]->entries[0]->title)->toContain('TT Adversaire');
});

it('annonce une assemblée générale mais garde le comité pour lui', function (): void {
    Meeting::factory()->create([
        'type' => MeetingTypeEnum::GENERAL_ASSEMBLY,
        'status' => MeetingStatusEnum::CONFIRMED,
        'title' => 'Assemblée générale de reprise',
        'scheduled_at' => '2026-09-15 19:30:00',
        'ends_at' => '2026-09-15 21:30:00',
    ]);

    Meeting::factory()->create([
        'type' => MeetingTypeEnum::COMMITTEE,
        'status' => MeetingStatusEnum::CONFIRMED,
        'title' => 'Réunion de comité — septembre',
        'scheduled_at' => '2026-09-16 18:00:00',
        'ends_at' => '2026-09-16 20:00:00',
    ]);

    $agenda = app(PublicAgendaService::class)->forHomepage();

    $titles = collect($agenda->days)->flatMap(fn ($day) => collect($day->entries)->pluck('title'));

    expect($titles)->toContain('Assemblée générale de reprise')
        ->and($titles)->not->toContain('Réunion de comité — septembre');
});

it('ignore une assemblée générale qui n’est pas encore confirmée', function (): void {
    Meeting::factory()->create([
        'type' => MeetingTypeEnum::GENERAL_ASSEMBLY,
        'status' => MeetingStatusEnum::PLANNING,
        'title' => 'AG en préparation',
        'scheduled_at' => '2026-09-15 19:30:00',
    ]);

    expect(app(PublicAgendaService::class)->forHomepage()->days)->toBeEmpty();
});

/**
 * Un stage occupe des jours consécutifs, un pack hebdomadaire revient chaque
 * semaine : le nombre de jours d'affilée suffit à les distinguer, sans nouveau
 * champ ni case à cocher.
 */
function summerCamp(Season $season, Room $room): TrainingPack
{
    return TrainingPack::factory()->create([
        'season_id' => $season->id,
        'room_id' => $room->id,
        'name' => "Stage d'été",
        'day_of_week' => null,
        'days_of_week' => [1, 2, 3, 4, 5],
        'start_time' => '09:00:00',
        'duration_minutes' => 420,
        'is_active' => true,
    ]);
}

it('groupe les journées consécutives d’un stage en un seul bloc', function (): void {
    $room = Room::factory()->create(['name' => 'Blocry G3']);
    $pack = summerCamp($this->season, $room);

    foreach (['09-07', '09-08', '09-09', '09-10', '09-11'] as $day) {
        Training::factory()->create([
            'season_id' => $this->season->id,
            'room_id' => $room->id,
            'training_pack_id' => $pack->id,
            'start' => "2026-{$day} 09:00:00",
            'end' => "2026-{$day} 16:00:00",
        ]);
    }

    $agenda = app(PublicAgendaService::class)->forHomepage();

    expect($agenda->days)->toHaveCount(1);

    $entry = $agenda->days[0]->entries[0];

    expect($entry->title)->toBe("Stage d'été")
        ->and($entry->startsAt->toDateString())->toBe('2026-09-07')
        ->and($entry->spansMultipleDays())->toBeTrue()
        ->and($entry->spanEndsOn?->toDateString())->toBe('2026-09-11')
        ->and($entry->spanExceptions)->toBeEmpty();
});

it('casse le bloc du stage sur la journée annulée', function (): void {
    $room = Room::factory()->create(['name' => 'Blocry G3']);
    $pack = summerCamp($this->season, $room);

    foreach (['09-07', '09-08', '09-09', '09-10', '09-11'] as $day) {
        Training::factory()->create([
            'season_id' => $this->season->id,
            'room_id' => $room->id,
            'training_pack_id' => $pack->id,
            'start' => "2026-{$day} 09:00:00",
            'end' => "2026-{$day} 16:00:00",
            'status' => $day === '09-09' ? 'cancelled_closed' : 'scheduled',
            'cancellation_note' => $day === '09-09' ? 'Jour férié' : null,
        ]);
    }

    $entry = app(PublicAgendaService::class)->forHomepage()->days[0]->entries[0];

    expect($entry->spanEndsOn?->toDateString())->toBe('2026-09-11')
        ->and($entry->spanExceptions)->toHaveCount(1)
        ->and($entry->spanExceptions[0]->startsAt->toDateString())->toBe('2026-09-09')
        ->and($entry->spanExceptions[0]->cancellationNote)->toBe('Jour férié');
});

it('ne groupe pas les séances hebdomadaires d’un même pack', function (): void {
    $room = Room::factory()->create(['name' => 'Demeester -1']);
    $pack = TrainingPack::factory()->create([
        'season_id' => $this->season->id,
        'room_id' => $room->id,
        'name' => 'Mardi — Perfectionnement',
        'day_of_week' => 2,
        'start_time' => '20:30:00',
        'duration_minutes' => 90,
        'is_active' => true,
    ]);

    foreach (['09-08', '09-15'] as $day) {
        Training::factory()->create([
            'season_id' => $this->season->id,
            'room_id' => $room->id,
            'training_pack_id' => $pack->id,
            'start' => "2026-{$day} 20:30:00",
            'end' => "2026-{$day} 22:00:00",
        ]);
    }

    $agenda = app(PublicAgendaService::class)->forHomepage();

    expect($agenda->days)->toHaveCount(2)
        ->and($agenda->days[0]->entries[0]->spansMultipleDays())->toBeFalse();
});

it('laisse hors de la fenêtre ce qui dépasse quatorze jours', function (): void {
    Training::factory()->create([
        'season_id' => $this->season->id,
        'start' => '2026-09-07 18:00:00',
        'end' => '2026-09-07 20:00:00',
    ]);

    Training::factory()->create([
        'season_id' => $this->season->id,
        'start' => '2026-09-28 18:00:00',
        'end' => '2026-09-28 20:00:00',
    ]);

    $agenda = app(PublicAgendaService::class)->forHomepage();

    expect($agenda->days)->toHaveCount(1)
        ->and($agenda->days[0]->date->toDateString())->toBe('2026-09-07')
        ->and($agenda->isExtended)->toBeFalse();
});

it('élargit la fenêtre jusqu’à la prochaine activité quand les quinze jours sont vides', function (): void {
    Training::factory()->create([
        'season_id' => $this->season->id,
        'start' => '2026-11-02 18:00:00',
        'end' => '2026-11-02 20:00:00',
    ]);

    $agenda = app(PublicAgendaService::class)->forHomepage();

    expect($agenda->isExtended)->toBeTrue()
        ->and($agenda->days)->toHaveCount(1)
        ->and($agenda->days[0]->date->toDateString())->toBe('2026-11-02');
});

it('rend un agenda vide quand plus rien n’est programmé', function (): void {
    $agenda = app(PublicAgendaService::class)->forHomepage();

    expect($agenda->days)->toBeEmpty()
        ->and($agenda->isExtended)->toBeFalse();
});

it('remonte les annulations en tête, y compris celles noyées dans un stage', function (): void {
    $room = Room::factory()->create(['name' => 'Blocry G3']);
    $pack = summerCamp($this->season, $room);

    foreach (['09-07', '09-08', '09-09'] as $day) {
        Training::factory()->create([
            'season_id' => $this->season->id,
            'room_id' => $room->id,
            'training_pack_id' => $pack->id,
            'start' => "2026-{$day} 09:00:00",
            'end' => "2026-{$day} 16:00:00",
            'status' => $day === '09-08' ? 'cancelled_closed' : 'scheduled',
            'cancellation_note' => $day === '09-08' ? 'Jour férié' : null,
        ]);
    }

    Training::factory()->create([
        'season_id' => $this->season->id,
        'room_id' => $room->id,
        'start' => '2026-09-15 20:30:00',
        'end' => '2026-09-15 22:00:00',
        'status' => 'cancelled_free',
        'cancellation_note' => 'Coach absent',
    ]);

    $agenda = app(PublicAgendaService::class)->forHomepage();

    expect($agenda->exceptions)->toHaveCount(2)
        ->and($agenda->exceptions[0]->startsAt->toDateString())->toBe('2026-09-08')
        ->and($agenda->exceptions[0]->cancellationNote)->toBe('Jour férié')
        ->and($agenda->exceptions[1]->startsAt->toDateString())->toBe('2026-09-15')
        ->and($agenda->exceptions[1]->roomStaysOpen())->toBeTrue();
});

it('n’expose aucune exception quand tout se tient', function (): void {
    Training::factory()->create([
        'season_id' => $this->season->id,
        'start' => '2026-09-07 18:00:00',
        'end' => '2026-09-07 20:00:00',
    ]);

    expect(app(PublicAgendaService::class)->forHomepage()->exceptions)->toBeEmpty();
});

it('fusionne les packs d’une même journée en une seule plage de rythme', function (): void {
    $room = Room::factory()->create(['name' => 'Demeester -1']);

    TrainingPack::factory()->create([
        'season_id' => $this->season->id, 'room_id' => $room->id, 'is_active' => true,
        'name' => 'Lundi — Supervisé', 'day_of_week' => 1, 'start_time' => '18:00:00', 'duration_minutes' => 120,
    ]);
    TrainingPack::factory()->create([
        'season_id' => $this->season->id, 'room_id' => $room->id, 'is_active' => true,
        'name' => 'Lundi — Entrée libre', 'day_of_week' => 1, 'start_time' => '20:00:00', 'duration_minutes' => 150,
    ]);
    TrainingPack::factory()->create([
        'season_id' => $this->season->id, 'room_id' => $room->id, 'is_active' => true,
        'name' => 'Mardi — Perfectionnement', 'day_of_week' => 2, 'start_time' => '20:30:00', 'duration_minutes' => 90,
    ]);

    $rhythm = app(PublicAgendaService::class)->forHomepage()->rhythm;

    expect($rhythm)->toHaveCount(2)
        ->and($rhythm[0]->dayOfWeek)->toBe(1)
        ->and($rhythm[0]->startsAt)->toBe('18:00')
        ->and($rhythm[0]->endsAt)->toBe('22:30')
        ->and($rhythm[1]->dayOfWeek)->toBe(2)
        ->and($rhythm[1]->startsAt)->toBe('20:30');
});

it('tient les stages et les packs inactifs hors du rythme habituel', function (): void {
    $room = Room::factory()->create(['name' => 'Blocry G3']);

    summerCamp($this->season, $room);

    TrainingPack::factory()->create([
        'season_id' => $this->season->id, 'room_id' => $room->id, 'is_active' => false,
        'name' => 'Jeudi — Ancien pack', 'day_of_week' => 4, 'start_time' => '19:00:00', 'duration_minutes' => 90,
    ]);

    expect(app(PublicAgendaService::class)->forHomepage()->rhythm)->toBeEmpty();
});

it('sort du rythme un pack dont la date de fin est passée', function (): void {
    $room = Room::factory()->create(['name' => 'Demeester -1']);

    TrainingPack::factory()->create([
        'season_id' => $this->season->id, 'room_id' => $room->id, 'is_active' => true,
        'name' => 'Lundi — Terminé hier', 'day_of_week' => 1,
        'start_time' => '18:00:00', 'duration_minutes' => 120,
        'pack_end_date' => '2026-09-03',
    ]);

    TrainingPack::factory()->create([
        'season_id' => $this->season->id, 'room_id' => $room->id, 'is_active' => true,
        'name' => 'Mardi — Toujours en cours', 'day_of_week' => 2,
        'start_time' => '20:30:00', 'duration_minutes' => 90,
        'pack_end_date' => '2027-06-30',
    ]);

    $rhythm = app(PublicAgendaService::class)->forHomepage($this->season)->rhythm;

    expect($rhythm)->toHaveCount(1)
        ->and($rhythm[0]->dayOfWeek)->toBe(2);
});

it('limite le rythme aux packs de la saison retenue', function (): void {
    $room = Room::factory()->create(['name' => 'Demeester -1']);

    $otherSeason = Season::factory()->create([
        'is_active' => false,
        'start_at' => '2025-09-01',
        'end_at' => '2026-06-30',
    ]);

    TrainingPack::factory()->create([
        'season_id' => $otherSeason->id, 'room_id' => $room->id, 'is_active' => true,
        'name' => 'Jeudi — Saison précédente', 'day_of_week' => 4,
        'start_time' => '19:00:00', 'duration_minutes' => 90,
    ]);

    TrainingPack::factory()->create([
        'season_id' => $this->season->id, 'room_id' => $room->id, 'is_active' => true,
        'name' => 'Mardi — Saison en cours', 'day_of_week' => 2,
        'start_time' => '20:30:00', 'duration_minutes' => 90,
    ]);

    $rhythm = app(PublicAgendaService::class)->forHomepage($this->season)->rhythm;

    expect($rhythm)->toHaveCount(1)
        ->and($rhythm[0]->dayOfWeek)->toBe(2);
});

it('porte la mention interclubs du rythme depuis les réglages du club', function (): void {
    AppSetting::set('interclub_schedule_day', 'Vendredi');
    AppSetting::set('interclub_schedule_time_start', '19:00');
    AppSetting::set('interclub_schedule_time_end', '23:30');

    $interclubRhythm = app(PublicAgendaService::class)->forHomepage($this->season)->interclubRhythm;

    expect($interclubRhythm)->not->toBeNull()
        ->and($interclubRhythm->day)->toBe('Vendredi')
        ->and($interclubRhythm->startsAt)->toBe('19:00')
        ->and($interclubRhythm->endsAt)->toBe('23:30');
});

it('tait la mention interclubs quand le club la désactive', function (): void {
    AppSetting::set('interclub_schedule_enabled', '0');

    expect(app(PublicAgendaService::class)->forHomepage($this->season)->interclubRhythm)->toBeNull();
});

it('tait la mention interclubs quand aucune saison ne porte le rythme', function (): void {
    AppSetting::set('interclub_schedule_enabled', '1');

    expect(app(PublicAgendaService::class)->forHomepage(null)->interclubRhythm)->toBeNull();
});

/**
 * Pas de cache sur ce bloc : une annulation périmée serait exactement le défaut
 * qu'il existe pour corriger. La contrepartie est que le coût doit rester plat,
 * d'où ce plafond — il tombe si un chargement paresseux se glisse dans une
 * boucle.
 */
it('interroge la base un nombre borné de fois, quel que soit le volume', function (): void {
    $room = Room::factory()->create(['name' => 'Demeester -1']);

    // Deux packs en alternance : aucun n'aligne trois jours d'affilée, donc rien
    // n'est replié en stage et l'on obtient bien douze journées distinctes.
    $packs = collect([2, 4])->map(fn (int $dayOfWeek): TrainingPack => TrainingPack::factory()->create([
        'season_id' => $this->season->id, 'room_id' => $room->id, 'is_active' => true,
        'name' => "Pack {$dayOfWeek}", 'day_of_week' => $dayOfWeek,
        'start_time' => '20:30:00', 'duration_minutes' => 90,
    ]));

    foreach (range(5, 16) as $day) {
        Training::factory()->create([
            'season_id' => $this->season->id,
            'room_id' => $room->id,
            'training_pack_id' => $packs[$day % 2]->id,
            'start' => sprintf('2026-09-%02d 20:30:00', $day),
            'end' => sprintf('2026-09-%02d 22:00:00', $day),
        ]);
    }

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $agenda = app(PublicAgendaService::class)->forHomepage($this->season);

    expect($agenda->days)->toHaveCount(12)
        ->and($queries)->toBeLessThanOrEqual(8);
});
