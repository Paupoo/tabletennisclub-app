<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Data\Interclub\DayAvailability;
use App\Data\Interclub\DayAvailabilityRow;
use App\Data\Interclub\DayTeam;
use App\Data\Interclub\LineupConstraint;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\LineupLegality;
use App\Domains\Shared\Enums\TeamLineupNeed;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Une journée d'interclubs vue d'en haut : tous ceux qui ont dit oui ou
 * peut-être, toutes équipes confondues.
 *
 * C'est l'écran du sélectionneur qui doit débloquer une équipe. Contrairement au
 * pool du tiroir ({@see InterclubPoolService}), rien n'y est tenu à l'écart : un
 * joueur retenu dans une composition pas encore publiée y figure aussi. Le pool
 * protège un capitaine des autres capitaines ; ici c'est celui qui arbitre qui
 * regarde.
 *
 * Une catégorie par bloc, parce qu'un indice de force n'a de sens que dans la
 * sienne.
 */
class InterclubDayAvailabilityService
{
    public function __construct(private readonly InterclubLineupLegalityService $legality) {}

    /**
     * La même lecture, sur les rencontres d'une journée déjà chargées.
     *
     * L'écran des sélections a toutes les rencontres de la saison sous la main
     * et se rend à chaque case cochée : les recharger pour un compteur coûtait
     * une dizaine de requêtes par clic.
     *
     * @param  EloquentCollection<int, Interclub>  $weekFixtures  une seule journée
     * @return Collection<int, DayAvailability>
     */
    public function forFixtures(EloquentCollection $weekFixtures, bool $withFillIns = true): Collection
    {
        $weekFixtures->loadMissing(['league', 'visitedTeam.club', 'visitedTeam.users', 'visitingTeam.club', 'visitingTeam.users', 'users']);

        return $weekFixtures
            ->reject(fn (Interclub $fixture): bool => (bool) $fixture->is_bye)
            ->groupBy(fn (Interclub $fixture): string => $fixture->league?->category ?? '')
            ->map(fn (EloquentCollection $fixtures, string $category): DayAvailability => $this->forCategory(
                LeagueCategory::fromName($category === '' ? null : $category),
                $fixtures,
                $withFillIns,
            ))
            ->values();
    }

    /**
     * @param  bool  $withFillIns  `false` laisse `canHelp` vide : les verdicts
     *                             C.22 coûtent des requêtes, et un résumé replié
     *                             n'en affiche aucun.
     * @return Collection<int, DayAvailability>
     */
    public function forWeek(Season $season, int $weekNumber, bool $withFillIns = true): Collection
    {
        return $this->forFixtures($this->fixturesOfWeek($season, $weekNumber), $withFillIns);
    }

    /**
     * @return EloquentCollection<int, Interclub>
     */
    private function fixturesOfWeek(Season $season, int $weekNumber): EloquentCollection
    {
        return Interclub::query()
            ->where('season_id', $season->id)
            ->where('week_number', $weekNumber)
            ->withoutForfeits()
            ->orderBy('interclubs.id')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, Interclub>  $fixtures
     */
    private function forCategory(?LeagueCategory $category, EloquentCollection $fixtures, bool $withFillIns): DayAvailability
    {
        $lineups = $this->lineupsOf($fixtures);

        $teams = $fixtures
            ->mapWithKeys(fn (Interclub $fixture): array => [$fixture->id => $this->teamOf($fixture, $lineups)])
            ->filter();

        $shortTeams = $withFillIns ? $this->shortTeams($fixtures, $teams, $category) : [];

        $players = $fixtures
            ->flatMap(function (Interclub $fixture) use ($category, $lineups, $shortTeams): array {
                $team = $this->ownTeamOf($fixture);

                if (! $team instanceof Team) {
                    return [];
                }

                return $fixture->users
                    ->filter(fn (User $player): bool => in_array($player->registration?->availability, [
                        InterclubAvailability::AVAILABLE->value,
                        InterclubAvailability::MAYBE->value,
                    ], true))
                    ->map(fn (User $player): DayAvailabilityRow => new DayAvailabilityRow(
                        user: $player,
                        forceIndex: $player->forceListFor($category),
                        teamName: $team->name,
                        availability: InterclubAvailability::from($player->registration->availability),
                        lineupTeamName: $lineups[$player->id]['team'] ?? null,
                        lineupPublished: $lineups[$player->id]['published'] ?? false,
                        canHelp: isset($lineups[$player->id])
                            ? []
                            : $this->whereCanHelp($player->forceListFor($category), $shortTeams),
                    ))
                    ->values()
                    ->all();
            })
            // Un joueur répond pour la rencontre de son équipe ; s'il est aligné
            // ailleurs, la ligne de pivot de l'autre rencontre peut porter une
            // réponse elle aussi. Une ligne par joueur, et le oui prime.
            ->sortBy(fn (DayAvailabilityRow $row): int => $row->availability === InterclubAvailability::AVAILABLE ? 0 : 1)
            ->unique(fn (DayAvailabilityRow $row): int => $row->user->id)
            ->sortBy(fn (DayAvailabilityRow $row): int => $row->forceIndex ?? PHP_INT_MAX)
            ->values();

        // Sans indice dans la catégorie, pas de place sur la feuille (C.18.2.2).
        [$ranked, $unranked] = $players->partition(fn (DayAvailabilityRow $row): bool => $row->forceIndex !== null);

        return new DayAvailability(
            category: $category,
            teams: $teams->sortBy(fn (DayTeam $team): string => $team->teamName)->values(),
            players: $ranked->values(),
            unrankedCount: $unranked->count(),
            silentCount: $this->silentCount($fixtures),
        );
    }

    /**
     * Où chacun est coché cette journée, et si la composition est partie.
     *
     * @param  EloquentCollection<int, Interclub>  $fixtures
     * @return array<int, array{team: string, published: bool}>
     */
    private function lineupsOf(EloquentCollection $fixtures): array
    {
        $lineups = [];

        foreach ($fixtures as $fixture) {
            $team = $this->ownTeamOf($fixture);

            if (! $team instanceof Team) {
                continue;
            }

            foreach ($fixture->users as $player) {
                if ($player->registration?->is_selected) {
                    $lineups[$player->id] = [
                        'team' => $team->name,
                        'published' => $player->registration->selection_confirmed_at !== null,
                    ];
                }
            }
        }

        return $lineups;
    }

    /**
     * Le camp du club dans cette rencontre. Le calendrier importé contient aussi
     * des rencontres entre deux clubs adverses : elles n'ont rien à dire ici.
     */
    private function ownTeamOf(Interclub $fixture): ?Team
    {
        foreach ([$fixture->visitedTeam, $fixture->visitingTeam] as $team) {
            if ($team?->club?->is_own_club) {
                return $team;
            }
        }

        return null;
    }

    /**
     * Les équipes en manque qui jouent encore, avec ce que C.22 leur impose.
     *
     * La contrainte coûte quelques requêtes par rencontre : on ne la calcule que
     * pour les équipes qui cherchent quelqu'un, pas pour toute la journée.
     *
     * @param  EloquentCollection<int, Interclub>  $fixtures
     * @param  Collection<int, DayTeam>  $teams
     * @return list<array{name: string, constraint: LineupConstraint, lineup: list<int|null>}>
     */
    private function shortTeams(EloquentCollection $fixtures, Collection $teams, ?LeagueCategory $category): array
    {
        return $fixtures
            ->filter(fn (Interclub $fixture): bool => ($teams[$fixture->id] ?? null)?->isShort() === true
                && $fixture->start_date_time >= now())
            ->map(fn (Interclub $fixture): array => [
                'name' => $teams[$fixture->id]->teamName,
                'constraint' => $this->legality->constraintFor($fixture),
                'lineup' => array_values($fixture->users
                    ->filter(fn (User $player): bool => (bool) $player->registration?->is_selected)
                    ->map(fn (User $player): ?int => $player->forceListFor($category))
                    ->all()),
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Les membres des équipes du club qui n'ont rien répondu pour la rencontre
     * de leur équipe — et que personne n'a cochés pour autant.
     *
     * @param  EloquentCollection<int, Interclub>  $fixtures
     */
    private function silentCount(EloquentCollection $fixtures): int
    {
        return $fixtures
            ->flatMap(function (Interclub $fixture): array {
                $team = $this->ownTeamOf($fixture);

                if (! $team instanceof Team) {
                    return [];
                }

                $answered = $fixture->users
                    ->filter(fn (User $player): bool => $player->registration?->availability !== null
                        || (bool) $player->registration?->is_selected)
                    ->pluck('id')
                    ->all();

                return $team->users
                    ->reject(fn (User $member): bool => in_array($member->id, $answered, true))
                    ->pluck('id')
                    ->all();
            })
            ->unique()
            ->count();
    }

    /**
     * @param  array<int, array{team: string, published: bool}>  $lineups
     */
    private function teamOf(Interclub $fixture, array $lineups): ?DayTeam
    {
        $team = $this->ownTeamOf($fixture);

        if (! $team instanceof Team) {
            return null;
        }

        $selectedCount = $fixture->users
            ->filter(fn (User $player): bool => (bool) $player->registration?->is_selected)
            ->count();

        // Les renforts possibles de l'intérieur : ceux de l'équipe qui ont dit
        // oui ou peut-être et que personne n'a encore cochés, ici ou ailleurs.
        $ownReserve = $fixture->users
            ->filter(fn (User $player): bool => ! isset($lineups[$player->id])
                && in_array($player->registration?->availability, [
                    InterclubAvailability::AVAILABLE->value,
                    InterclubAvailability::MAYBE->value,
                ], true))
            ->count();

        $need = match (true) {
            $selectedCount >= $fixture->total_players => TeamLineupNeed::COMPLETE,
            $selectedCount + $ownReserve >= $fixture->total_players => TeamLineupNeed::COVERABLE,
            default => TeamLineupNeed::UNCOVERED,
        };

        return new DayTeam(
            fixtureId: $fixture->id,
            teamName: $team->name,
            selectedCount: $selectedCount,
            totalPlayers: $fixture->total_players,
            need: $need,
            isShortHanded: $fixture->isShortHanded(),
        );
    }

    /**
     * Les équipes en manque où ce joueur peut être ajouté sans enfreindre C.22.
     *
     * Un ordre illisible des équipes ne dit rien contre personne : comme dans le
     * tiroir, la règle se tait plutôt que de deviner.
     *
     * @param  list<array{name: string, constraint: LineupConstraint, lineup: list<int|null>}>  $shortTeams
     * @return array<string, bool>
     */
    private function whereCanHelp(?int $forceIndex, array $shortTeams): array
    {
        $canHelp = [];

        foreach ($shortTeams as $team) {
            $state = $team['constraint']->rankIsReadable
                ? $this->legality->verdictFor($forceIndex, $team['lineup'], $team['constraint']->bounds())->state
                : LineupLegality::NOT_APPLICABLE;

            if ($state !== LineupLegality::FORBIDDEN) {
                $canHelp[$team['name']] = $state === LineupLegality::UNCERTAIN;
            }
        }

        return $canHelp;
    }
}
