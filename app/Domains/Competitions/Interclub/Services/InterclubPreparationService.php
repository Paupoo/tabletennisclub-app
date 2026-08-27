<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * How ready the club is for what is still to be played.
 *
 * The selections screen and the control center both answered this question, in
 * two different ways, and were free to drift apart — one rated a week on its
 * earliest fixture, the other on whichever row the engine happened to return.
 * The rule lives here now, and both read it.
 */
class InterclubPreparationService
{
    /**
     * @param  EloquentCollection<int, Interclub>  $fixtures
     * @return EloquentCollection<int, Interclub>
     */
    public function fixturesForTeam(EloquentCollection $fixtures, int $teamId): EloquentCollection
    {
        return $fixtures->filter(fn (Interclub $ic): bool => $ic->visited_team_id === $teamId
            || $ic->visiting_team_id === $teamId)->values();
    }

    /**
     * Where a single fixture stands. Ordered by how much attention it needs.
     */
    public function fixtureStatus(Interclub $interclub): string
    {
        if ($interclub->start_date_time < now()) {
            return 'past';
        }

        $users = $interclub->users;
        $maxPlayers = $interclub->total_players;

        $confirmedCount = $users->filter(fn ($u): bool => $u->registration?->is_selected && $u->registration?->selection_confirmed_at)->count();
        $selectedCount = $users->filter(fn ($u) => $u->registration?->is_selected)->count();
        $availableCount = $users->filter(fn ($u): bool => $u->registration?->availability === 'available')->count();
        $daysUntil = (int) now()->diffInDays($interclub->start_date_time, false);

        return match (true) {
            $confirmedCount > 0 => 'confirmed',
            $selectedCount >= $maxPlayers => 'actionable',
            $availableCount >= $maxPlayers => 'actionable',
            $daysUntil <= 14 => 'urgent',
            default => 'future',
        };
    }

    /**
     * The season at a glance: one row per team, one column per match day.
     *
     * @param  EloquentCollection<int, Team>  $teams
     * @param  EloquentCollection<int, Interclub>  $fixtures
     * @return array{weeks: array<int, array{wk: int, status: string}>, preparation_score: int, total: int, ok: int, matrix: array<int, array<int, string|null>>, teams: array<int, array{id: int, name: string}>}
     */
    public function summary(EloquentCollection $teams, EloquentCollection $fixtures): array
    {
        $weekNumbers = $this->weekNumbers($teams, $fixtures);

        $weeks = $weekNumbers->map(fn (int $wk): array => [
            'wk' => $wk,
            'status' => $this->weekStatus($wk, $teams, $fixtures),
        ])->values()->all();

        // A week everyone has already played is behind us, not prepared: it
        // leaves the score entirely rather than counting as ready. The score
        // therefore reads "ready out of what is left", and its denominator
        // shrinks as the season goes.
        $scored = collect($weeks)->reject(fn (array $w): bool => $w['status'] === 'past');

        $total = $scored->count();
        $ok = $scored->where('status', 'confirmed')->count();

        $matrix = $this->teamWeekMatrix($teams, $weekNumbers, $fixtures);

        // Trois catégories réutilisent les mêmes lettres : « A » ne veut rien dire
        // sans sa catégorie et sa division.
        $teamRows = $teams->map(fn (Team $t): array => [
            'id' => $t->id,
            'name' => $t->name,
            'division' => $t->league?->division,
            'category' => $t->league?->category,
        ])->sortBy([
            fn (array $a, array $b): int => $this->categoryRank($a['category']) <=> $this->categoryRank($b['category']),
            fn (array $a, array $b): int => $a['name'] <=> $b['name'],
        ])->values()->all();

        $categoryWeeks = $this->categoryWeeks($teams, $fixtures);

        return [
            'weeks' => $weeks,
            'preparation_score' => $total > 0 ? (int) round($ok / $total * 100) : 0,
            'total' => $total,
            'ok' => $ok,
            'matrix' => $matrix,
            'teams' => $teamRows,
            // Une semaine absente de cette liste est une semaine de repos pour la
            // catégorie — pas une rencontre manquante. Les deux se ressemblaient.
            'category_weeks' => $categoryWeeks,
            // Sur un téléphone on ne scanne pas un motif : on vient savoir où en
            // est le club. D'où un bilan — trois chiffres et un état par
            // catégorie — plutôt qu'une transposition de la grille.
            'week_rows' => $weekRows = $this->weekRows($teamRows, $weekNumbers, $categoryWeeks, $matrix, $fixtures),
            'kpi' => $this->kpi($weekRows),
            'categories' => $this->categoryStandings($teamRows, $weekRows),
        ];
    }

    /**
     * Worst status across the teams playing that week. A week where every
     * fixture has been played reports 'past' so it can leave the preparation
     * score rather than inflate it.
     *
     * @param  EloquentCollection<int, Team>  $teams
     * @param  EloquentCollection<int, Interclub>  $fixtures
     */
    public function weekStatus(int $weekNumber, EloquentCollection $teams, EloquentCollection $fixtures): string
    {
        $liveStatus = null;
        $sawPlayedFixture = false;

        foreach ($teams as $team) {
            // Fixtures are ordered by kick-off, so a team playing twice in one
            // week is rated on its earliest fixture. The query this replaced
            // had no ORDER BY and picked whichever row the engine returned.
            $interclub = $this->fixturesForTeam($fixtures, $team->id)
                ->firstWhere('week_number', $weekNumber);

            if (! $interclub) {
                continue;
            }

            $status = $this->fixtureStatus($interclub);

            if ($status === 'past') {
                $sawPlayedFixture = true;

                continue;
            }

            $liveStatus = $this->worstOf($liveStatus ?? 'confirmed', $status);
        }

        // A single fixture still to play decides the week; 'past' is reserved
        // for weeks where there is nothing left to prepare.
        if ($liveStatus !== null) {
            return $liveStatus;
        }

        return $sawPlayedFixture ? 'past' : 'confirmed';
    }

    /** L'ordre d'affichage des catégories : seniors, dames, vétérans. */
    private function categoryRank(?string $category): int
    {
        return ['MEN' => 0, 'WOMEN' => 1, 'VETERANS' => 2][$category] ?? 99;
    }

    /**
     * Où en est chaque catégorie : de quoi écrire un verdict en toutes lettres
     * plutôt que de l'encoder dans une pastille de huit pixels.
     *
     * @param  array<int, array<string, mixed>>  $teamRows
     * @param  array<int, array<string, mixed>>  $weekRows
     * @return array<int, array<string, mixed>>
     */
    private function categoryStandings(array $teamRows, array $weekRows): array
    {
        $standings = [];

        foreach ($teamRows as $team) {
            $category = $team['category'] ?? '—';
            $standings[$category] ??= ['category' => $category, 'teams' => 0];
            $standings[$category]['teams']++;
        }

        foreach ($standings as $category => &$standing) {
            $rows = array_values(array_filter($weekRows, fn (array $r): bool => $r['category'] === $category));
            $live = array_filter($rows, fn (array $r): bool => $r['status'] !== 'past');
            $next = collect($live)->sortBy('starts_at')->first();

            $standing['total'] = count($rows);
            $standing['played'] = count($rows) - count($live);
            $standing['todo'] = count(array_filter($live, fn (array $r): bool => in_array($r['status'], ['urgent', 'actionable'], true)));
            $standing['controlled'] = count(array_filter($live, fn (array $r): bool => $r['status'] === 'confirmed'));
            // Les segments de la barre de progression, dans l'ordre du calendrier.
            $standing['segments'] = array_column($rows, 'status');
            $standing['next_date'] = $next['date'] ?? null;
            $standing['next_status'] = $next['status'] ?? null;
        }

        return array_values($standings);
    }

    /**
     * Les semaines que chaque catégorie joue réellement.
     *
     * @param  EloquentCollection<int, Team>  $teams
     * @param  EloquentCollection<int, Interclub>  $fixtures
     * @return array<string, array<int, int>>
     */
    private function categoryWeeks(EloquentCollection $teams, EloquentCollection $fixtures): array
    {
        $weeks = [];

        foreach ($teams as $team) {
            $category = $team->league?->category ?? '—';

            foreach ($this->fixturesForTeam($fixtures, $team->id) as $fixture) {
                if ($fixture->week_number === null) {
                    continue;
                }

                $weeks[$category][$fixture->week_number] = true;
            }
        }

        return array_map(
            function (array $set): array {
                $list = array_keys($set);
                sort($list);

                return $list;
            },
            $weeks,
        );
    }

    /**
     * Les trois chiffres du haut : ce qui demande une action, ce qui est réglé,
     * ce qui arrive sans rien réclamer encore. Le passé n'y figure pas.
     *
     * @param  array<int, array<string, mixed>>  $weekRows
     * @return array{todo: int, controlled: int, upcoming: int}
     */
    private function kpi(array $weekRows): array
    {
        $live = array_filter($weekRows, fn (array $r): bool => $r['status'] !== 'past');

        return [
            'todo' => count(array_filter($live, fn (array $r): bool => in_array($r['status'], ['urgent', 'actionable'], true))),
            'controlled' => count(array_filter($live, fn (array $r): bool => $r['status'] === 'confirmed')),
            'upcoming' => count(array_filter($live, fn (array $r): bool => $r['status'] === 'future')),
        ];
    }

    /**
     * @param  EloquentCollection<int, Team>  $teams
     * @param  EloquentCollection<int, Interclub>  $fixtures
     * @return array<int, array<int, string|null>>
     */
    private function teamWeekMatrix(EloquentCollection $teams, Collection $weekNumbers, EloquentCollection $fixtures): array
    {
        $matrix = [];

        foreach ($teams as $team) {
            $teamFixtures = $this->fixturesForTeam($fixtures, $team->id);
            $matrix[$team->id] = [];

            foreach ($weekNumbers as $wk) {
                $matrix[$team->id][$wk] = $teamFixtures->firstWhere('week_number', $wk)
                    ? $this->weekStatus($wk, Team::newModelInstance()->newCollection([$team]), $fixtures)
                    : null;
            }
        }

        return $matrix;
    }

    /**
     * @param  EloquentCollection<int, Team>  $teams
     * @param  EloquentCollection<int, Interclub>  $fixtures
     * @return Collection<int, int>
     */
    private function weekNumbers(EloquentCollection $teams, EloquentCollection $fixtures): Collection
    {
        $teamIds = $teams->pluck('id')->all();

        // Ordonnées par le premier coup d'envoi de la semaine — c'est l'ordre que
        // `matchDayMap` numérote, donc colonnes et libellés restent en phase.
        // S'appuyer sur le simple ordre d'apparition suffisait tant que deux
        // catégories ne partageaient pas une date ; trier par numéro de semaine
        // désynchronisait tout dès qu'elles alternent.
        return $fixtures
            ->filter(fn (Interclub $ic): bool => in_array($ic->visited_team_id, $teamIds, true)
                || in_array($ic->visiting_team_id, $teamIds, true))
            ->reject(fn (Interclub $ic): bool => $ic->week_number === null)
            ->groupBy('week_number')
            ->map(fn (Collection $weekFixtures): int => $weekFixtures->min('start_date_time')->getTimestamp())
            ->sort()
            ->keys()
            ->map(fn ($wk): int => (int) $wk)
            ->values();
    }

    /**
     * La matrice transposée : une ligne par semaine et par catégorie qui joue.
     *
     * @param  array<int, array<string, mixed>>  $teamRows
     * @param  Collection<int, int>  $weekNumbers
     * @param  array<string, array<int, int>>  $categoryWeeks
     * @param  array<int, array<int, string|null>>  $matrix
     * @param  EloquentCollection<int, Interclub>  $fixtures
     * @return array<int, array<string, mixed>>
     */
    private function weekRows(array $teamRows, Collection $weekNumbers, array $categoryWeeks, array $matrix, EloquentCollection $fixtures): array
    {
        $rows = [];

        foreach ($weekNumbers as $week) {
            foreach ($categoryWeeks as $category => $playedWeeks) {
                if (! in_array($week, $playedWeeks, true)) {
                    continue;
                }

                $cells = [];

                foreach ($teamRows as $team) {
                    if (($team['category'] ?? '—') !== $category) {
                        continue;
                    }

                    $status = $matrix[$team['id']][$week] ?? null;

                    if ($status === null) {
                        continue;
                    }

                    $cells[] = [
                        'team_id' => $team['id'],
                        'name' => $team['name'],
                        'division' => $team['division'],
                        'status' => $status,
                    ];
                }

                if ($cells === []) {
                    continue;
                }

                $status = $this->worstOfMany(array_column($cells, 'status'));

                $kickOff = $fixtures
                    ->filter(fn (Interclub $ic): bool => $ic->week_number === $week
                        && $ic->league?->category === $category)
                    ->min('start_date_time');

                $rows[] = [
                    'wk' => $week,
                    'category' => $category,
                    'status' => $status,
                    'is_past' => $status === 'past',
                    'date' => $kickOff?->format('d/m'),
                    'starts_at' => $kickOff?->getTimestamp() ?? 0,
                    // Ce qui reste à faire sur cette journée : le chiffre que le
                    // bilan met en avant.
                    'to_compose' => count(array_filter(
                        $cells,
                        fn (array $c): bool => in_array($c['status'], ['urgent', 'actionable'], true),
                    )),
                    'cells' => $cells,
                ];
            }
        }

        return $rows;
    }

    /**
     * Ordered by how much attention the week needs. A distant fixture with
     * nothing done asks less of a selector than one that could be composed
     * right now, so 'actionable' outranks 'future' — it used to be the other
     * way round, and a week with real work to do showed up as quiet.
     */
    private function worstOf(string $a, string $b): string
    {
        $rank = ['confirmed' => 0, 'future' => 1, 'actionable' => 2, 'urgent' => 3];

        return ($rank[$b] ?? 0) > ($rank[$a] ?? 0) ? $b : $a;
    }

    /** @param array<int, string> $statuses */
    private function worstOfMany(array $statuses): string
    {
        $live = array_values(array_filter($statuses, fn (string $s): bool => $s !== 'past'));

        if ($live === []) {
            return 'past';
        }

        return array_reduce($live, fn (string $carry, string $s): string => $this->worstOf($carry, $s), 'confirmed');
    }
}
