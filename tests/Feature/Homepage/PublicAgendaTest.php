<?php

declare(strict_types=1);

use App\Data\PublicAgenda\AgendaDay;
use App\Data\PublicAgenda\PublicAgenda;
use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\AgendaFamily;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
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

/** La grille porte les 35 jours de la fenêtre : on vise une date, pas un rang. */
function dayOn(PublicAgenda $agenda, string $date): AgendaDay
{
    foreach ($agenda->days as $day) {
        if ($day->date->toDateString() === $date) {
            return $day;
        }
    }

    throw new RuntimeException("Aucun jour {$date} dans la grille.");
}

/** @return list<AgendaDay> */
function busyDays(PublicAgenda $agenda): array
{
    return array_values(array_filter($agenda->days, fn (AgendaDay $day): bool => $day->entries !== []));
}

it('couvre cinq semaines pleines à partir du lundi de la semaine en cours', function (): void {
    $agenda = app(PublicAgendaService::class)->forHomepage($this->season);

    expect($agenda->days)->toHaveCount(35)
        ->and($agenda->days[0]->date->toDateString())->toBe('2026-08-31')
        ->and($agenda->days[0]->date->isMonday())->toBeTrue()
        ->and($agenda->days[34]->date->toDateString())->toBe('2026-10-04');
});

it('marque le jour du jour et les jours déjà passés', function (): void {
    $agenda = app(PublicAgendaService::class)->forHomepage($this->season);

    // 2026-09-04 est un vendredi : lundi 31/08 à jeudi 03/09 sont passés.
    expect($agenda->days[0]->isPast)->toBeTrue()
        ->and($agenda->days[3]->isPast)->toBeTrue()
        ->and($agenda->days[4]->isToday)->toBeTrue()
        ->and($agenda->days[4]->isPast)->toBeFalse()
        ->and($agenda->days[5]->isPast)->toBeFalse();
});

it('liste une séance planifiée dans la fenêtre', function (): void {
    $room = Room::factory()->create(['name' => 'Demeester -1']);

    Training::factory()->create([
        'season_id' => $this->season->id,
        'room_id' => $room->id,
        'start' => '2026-09-07 18:00:00',
        'end' => '2026-09-07 20:00:00',
    ]);

    $agenda = app(PublicAgendaService::class)->forHomepage();

    expect(busyDays($agenda))->toHaveCount(1);

    $day = dayOn($agenda, '2026-09-07');
    expect($day->entries)->toHaveCount(1)
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

    $entry = dayOn($agenda, '2026-09-15')->entries[0];

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

    $entry = dayOn(app(PublicAgendaService::class)->forHomepage(), '2026-09-14')->entries[0];

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

    expect(busyDays($agenda))->toHaveCount(1)
        ->and(busyDays($agenda)[0]->date->toDateString())->toBe('2026-09-11')
        ->and(dayOn($agenda, '2026-09-11')->entries[0]->title)->toContain('TT Adversaire');
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

    expect(busyDays(app(PublicAgendaService::class)->forHomepage()))->toBeEmpty();
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
            'type' => 'Directed',
            'start' => "2026-{$day} 09:00:00",
            'end' => "2026-{$day} 16:00:00",
        ]);
    }

    $agenda = app(PublicAgendaService::class)->forHomepage();

    expect(busyDays($agenda))->toHaveCount(1);

    $entry = dayOn($agenda, '2026-09-07')->entries[0];

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
            'type' => 'Directed',
            'start' => "2026-{$day} 09:00:00",
            'end' => "2026-{$day} 16:00:00",
            'status' => $day === '09-09' ? 'cancelled_closed' : 'scheduled',
            'cancellation_note' => $day === '09-09' ? 'Jour férié' : null,
        ]);
    }

    $entry = dayOn(app(PublicAgendaService::class)->forHomepage(), '2026-09-07')->entries[0];

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

    expect(busyDays($agenda))->toHaveCount(2)
        ->and(dayOn($agenda, '2026-09-08')->entries[0]->spansMultipleDays())->toBeFalse();
});

it('laisse hors de la grille ce qui dépasse les cinq semaines', function (): void {
    Training::factory()->create([
        'season_id' => $this->season->id,
        'start' => '2026-09-07 18:00:00',
        'end' => '2026-09-07 20:00:00',
    ]);

    // La grille court du 31/08 au 04/10 : le 10 octobre est dehors.
    Training::factory()->create([
        'season_id' => $this->season->id,
        'start' => '2026-10-10 18:00:00',
        'end' => '2026-10-10 20:00:00',
    ]);

    $agenda = app(PublicAgendaService::class)->forHomepage($this->season);

    expect(busyDays($agenda))->toHaveCount(1)
        ->and(busyDays($agenda)[0]->date->toDateString())->toBe('2026-09-07');
});

it('garde ses trente-cinq cases même quand rien n’est programmé', function (): void {
    $agenda = app(PublicAgendaService::class)->forHomepage($this->season);

    expect($agenda->days)->toHaveCount(35)
        ->and(busyDays($agenda))->toBeEmpty();
});

it('remonte les annulations en tête, y compris celles noyées dans un stage', function (): void {
    $room = Room::factory()->create(['name' => 'Blocry G3']);
    $pack = summerCamp($this->season, $room);

    foreach (['09-07', '09-08', '09-09'] as $day) {
        Training::factory()->create([
            'season_id' => $this->season->id,
            'room_id' => $room->id,
            'training_pack_id' => $pack->id,
            'type' => 'Directed',
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

    expect(busyDays($agenda))->toHaveCount(12)
        ->and($queries)->toBeLessThanOrEqual(8);
});

it('range chaque activité dans une des trois familles', function (): void {
    $room = Room::factory()->create(['name' => 'Demeester -1']);
    $ourClub = Club::factory()->ownClub()->create(['name' => 'CTT Ottignies-Blocry']);
    $rival = Club::factory()->create(['name' => 'TT Adversaire']);
    $ourTeam = Team::factory()->create(['club_id' => $ourClub->id, 'season_id' => $this->season->id, 'name' => 'A']);
    $theirTeam = Team::factory()->create(['club_id' => $rival->id, 'season_id' => $this->season->id, 'name' => 'C']);

    Training::factory()->create([
        'season_id' => $this->season->id, 'room_id' => $room->id,
        'start' => '2026-09-07 18:00:00', 'end' => '2026-09-07 20:00:00',
    ]);

    Interclub::factory()->create([
        'season_id' => $this->season->id,
        'visited_team_id' => $ourTeam->id, 'visiting_team_id' => $theirTeam->id,
        'start_date_time' => '2026-09-11 20:00:00', 'is_bye' => false,
    ]);

    Meeting::factory()->create([
        'type' => MeetingTypeEnum::GENERAL_ASSEMBLY,
        'status' => MeetingStatusEnum::CONFIRMED,
        'title' => 'Assemblée générale',
        'scheduled_at' => '2026-09-15 19:30:00',
    ]);

    $agenda = app(PublicAgendaService::class)->forHomepage($this->season);

    expect(dayOn($agenda, '2026-09-07')->entries[0]->family)->toBe(AgendaFamily::TRAINING)
        ->and(dayOn($agenda, '2026-09-11')->entries[0]->family)->toBe(AgendaFamily::COMPETITION)
        ->and(dayOn($agenda, '2026-09-15')->entries[0]->family)->toBe(AgendaFamily::CLUB_LIFE);
});

it('annonce un tournoi qui est sur le calendrier et tait celui qui n’y est pas', function (): void {
    Tournament::factory()->create([
        'name' => 'Tournoi du club',
        'start_date' => '2026-09-11',
        'start_time' => '09:00:00',
        'status' => TournamentStatusEnum::PUBLISHED,
    ]);

    Tournament::factory()->create([
        'name' => 'Brouillon interne',
        'start_date' => '2026-09-12',
        'start_time' => '09:00:00',
        'status' => TournamentStatusEnum::DRAFT,
    ]);

    Tournament::factory()->create([
        'name' => 'Tournoi annulé',
        'start_date' => '2026-09-13',
        'start_time' => '09:00:00',
        'status' => TournamentStatusEnum::CANCELLED,
    ]);

    $agenda = app(PublicAgendaService::class)->forHomepage($this->season);

    expect(busyDays($agenda))->toHaveCount(1)
        ->and(dayOn($agenda, '2026-09-11')->entries[0]->title)->toBe('Tournoi du club')
        ->and(dayOn($agenda, '2026-09-11')->entries[0]->family)->toBe(AgendaFamily::COMPETITION);
});

it('condense en une entrée les matches à domicile d’une même journée', function (): void {
    $ourClub = Club::factory()->ownClub()->create(['name' => 'CTT Ottignies-Blocry']);
    $rival = Club::factory()->create(['name' => 'TT Adversaire']);
    $teamC = Team::factory()->create(['club_id' => $ourClub->id, 'season_id' => $this->season->id, 'name' => 'C']);
    $teamD = Team::factory()->create(['club_id' => $ourClub->id, 'season_id' => $this->season->id, 'name' => 'D']);
    $theirs = Team::factory()->create(['club_id' => $rival->id, 'season_id' => $this->season->id, 'name' => 'X']);

    // Le cas fréquent : deux équipes reçoivent le même samedi, à deux heures.
    Interclub::factory()->create([
        'season_id' => $this->season->id, 'visited_team_id' => $teamC->id,
        'visiting_team_id' => $theirs->id, 'start_date_time' => '2026-09-19 14:00:00', 'is_bye' => false,
    ]);
    Interclub::factory()->create([
        'season_id' => $this->season->id, 'visited_team_id' => $teamD->id,
        'visiting_team_id' => $theirs->id, 'start_date_time' => '2026-09-19 20:00:00', 'is_bye' => false,
    ]);

    $day = dayOn(app(PublicAgendaService::class)->forHomepage($this->season), '2026-09-19');

    expect($day->entries)->toHaveCount(1)
        ->and($day->entries[0]->startsAt->format('H:i'))->toBe('14:00')
        ->and($day->entries[0]->title)->toBe('Interclubs · 2 matches à domicile');
});

it('nomme l’adversaire quand un seul match a lieu ce jour-là', function (): void {
    $ourClub = Club::factory()->ownClub()->create(['name' => 'CTT Ottignies-Blocry']);
    $rival = Club::factory()->create(['name' => 'Arc-en-Ciel CTT']);
    $ourTeam = Team::factory()->create(['club_id' => $ourClub->id, 'season_id' => $this->season->id, 'name' => 'A']);
    $theirs = Team::factory()->create(['club_id' => $rival->id, 'season_id' => $this->season->id, 'name' => 'F']);

    Interclub::factory()->create([
        'season_id' => $this->season->id, 'visited_team_id' => $ourTeam->id,
        'visiting_team_id' => $theirs->id, 'start_date_time' => '2026-09-18 20:00:00', 'is_bye' => false,
    ]);

    $day = dayOn(app(PublicAgendaService::class)->forHomepage($this->season), '2026-09-18');

    expect($day->entries)->toHaveCount(1)
        ->and($day->entries[0]->title)->toContain('Arc-en-Ciel');
});

it('retire le préfixe du jour que le club met dans le nom de ses packs', function (): void {
    $room = Room::factory()->create(['name' => 'Blocry G3']);
    $pack = TrainingPack::factory()->create([
        'season_id' => $this->season->id, 'room_id' => $room->id, 'is_active' => true,
        'name' => 'Lundi — Entraînement supervisé', 'day_of_week' => 1,
        'start_time' => '18:00:00', 'duration_minutes' => 120,
    ]);

    Training::factory()->create([
        'season_id' => $this->season->id, 'room_id' => $room->id, 'training_pack_id' => $pack->id,
        'start' => '2026-09-07 18:00:00', 'end' => '2026-09-07 20:00:00',
    ]);

    // La case porte déjà « LUNDI 07 » : répéter le jour dans le titre le dit deux fois.
    expect(dayOn(app(PublicAgendaService::class)->forHomepage($this->season), '2026-09-07')->entries[0]->title)
        ->toBe('Entraînement supervisé');
});

it('fusionne en une entrée les entrées libres d’une même journée', function (): void {
    $dem0 = Room::factory()->create(['name' => 'Demeester 0']);
    $dem1 = Room::factory()->create(['name' => 'Demeester -1']);

    foreach ([[$dem0, '20:00', 150], [$dem1, '20:30', 90]] as [$room, $time, $minutes]) {
        $pack = TrainingPack::factory()->create([
            'season_id' => $this->season->id, 'room_id' => $room->id, 'is_active' => true,
            'name' => "Lundi — Entrée libre ({$room->name})", 'day_of_week' => 1,
            'start_time' => $time . ':00', 'duration_minutes' => $minutes,
        ]);

        Training::factory()->create([
            'season_id' => $this->season->id, 'room_id' => $room->id, 'training_pack_id' => $pack->id,
            'type' => 'Free',
            'start' => "2026-09-07 {$time}:00",
            'end' => '2026-09-07 22:30:00',
        ]);
    }

    // Deux salles d'une même offre : les afficher deux fois n'ajoute rien.
    $day = dayOn(app(PublicAgendaService::class)->forHomepage($this->season), '2026-09-07');

    expect($day->entries)->toHaveCount(1)
        ->and($day->entries[0]->startsAt->format('H:i'))->toBe('20:00')
        ->and($day->entries[0]->title)->toBe('Entrée libre');
});
