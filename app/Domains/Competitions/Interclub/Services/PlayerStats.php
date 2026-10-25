<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\ExpectedWinRate;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who wins, against whom, and who plays — the figures the committee decides
 * selections, noyaux and youth entries on.
 *
 * Performance is measured against the odds rather than as a raw rate: every
 * match carries an expected share of wins read from the ranking gap
 * ({@see ExpectedWinRate}), and the score is what the player won beyond it. A
 * raw rate would crown whoever plays in the weakest division.
 *
 * Interclub and tournaments are always tallied apart and only summed in a third
 * figure that keeps both halves beside it, as on the member's own record
 * ({@see MemberResults}). Forfeits and the double never count towards a rate or
 * a score: nobody played them.
 */
class PlayerStats
{
    /**
     * Matches on each side of the "recent form" comparison.
     */
    public const int FORM_WINDOW = 10;

    public const array KINDS = ['interclub', 'tournaments', 'both'];

    /**
     * Everything about one member, over the filter's period, for one kind of
     * competition.
     *
     * @param  'interclub'|'tournaments'|'both'  $kind
     * @return array{by_gap: array<int, array{played: int, won: int, expected: float}>, perfs: array<int, array<string, mixed>>, contre_perfs: array<int, array<string, mixed>>, opponents: array<int, array{name: string, played: int, won: int, ranking: string|null}>, by_team: array<int, array<string, mixed>>, ranking_history: array<int, array{date: string, ranking: string}>, monthly: array<int, array<string, mixed>>, by_season: array<int, array<string, mixed>>}
     */
    public function player(User $member, PlayerStatsFilter $filter, string $kind): array
    {
        $table = ExpectedWinRate::table();
        $interclub = $kind === 'tournaments' ? collect() : $this->interclubLines([$member->id]);
        $tournaments = $kind === 'interclub' ? collect() : $this->tournamentLines([$member->id]);
        $all = $interclub->concat($tournaments)
            ->each(function (object $line): void {
                $line->gap = RankingScale::gap($line->our_ranking, $line->opponent_ranking);
            })
            ->sortBy('played_at')
            ->values();
        $lines = $all->filter($this->periodFor($filter))->values();
        $played = $lines->filter(fn (object $line): bool => ! $line->is_forfeit)->values();

        return [
            'by_gap' => $this->byGap($played, $table),
            'perfs' => $this->entries($played->filter(fn (object $line): bool => $line->gap > 0 && $line->we_won)),
            'contre_perfs' => $this->entries($played->filter(fn (object $line): bool => $line->gap !== null && $line->gap < 0 && ! $line->we_won)),
            'opponents' => $this->opponents($played),
            'by_team' => $this->byTeam($played->whereNotNull('interclub_id'), $table),
            'ranking_history' => $this->rankingHistory($all),
            'monthly' => $this->grouped($played, fn (object $line): string => substr((string) $line->played_at, 0, 7), $table),
            'by_season' => $this->grouped(
                $all->filter(fn (object $line): bool => ! $line->is_forfeit),
                fn (object $line): string => (string) $line->season_id,
                $table,
                Season::query()->pluck('name', 'id')->all(),
            ),
        ];
    }

    /**
     * The members the figures are about.
     *
     * Read from the members rather than from the matches, or "who plays the
     * least" would never show the competitor who has not played at all — the
     * one the committee most needs to see. Former members come back only on
     * request, archived ones included; an anonymised member never does, by
     * name or otherwise.
     *
     * @return Builder<User>
     */
    public function population(PlayerStatsFilter $filter): Builder
    {
        return User::query()
            ->when(
                $filter->includeFormer,
                fn (Builder $query): Builder => $query->withTrashed()->where(fn (Builder $either): Builder => $either
                    ->competitor()
                    ->orWhereIn('id', DB::table('interclub_individual_matches')->whereNotNull('user_id')->select('user_id'))
                    ->orWhereIn('id', DB::table('official_tournament_matches')->whereNotNull('user_id')->select('user_id'))
                ),
                fn (Builder $query): Builder => $query->competitor(),
            )
            ->where(fn (Builder $named): Builder => $named
                ->whereNull('email')
                ->orWhere('email', 'not like', 'deleted-%@anonymous.local'))
            ->when($filter->rankings !== [], fn (Builder $query): Builder => $query->whereIn('ranking', $filter->rankings))
            ->when($filter->gender !== null, fn (Builder $query): Builder => $query->where('gender', $filter->gender))
            ->when($filter->bornFrom !== null, fn (Builder $query): Builder => $query->whereDate('birthdate', '>=', $filter->bornFrom . '-01-01'))
            ->when($filter->bornTo !== null, fn (Builder $query): Builder => $query->whereDate('birthdate', '<=', $filter->bornTo . '-12-31'))
            ->when($filter->teamId !== null, fn (Builder $query): Builder => $query->whereHas('teams', fn (Builder $team): Builder => $team->where('teams.id', $filter->teamId)))
            ->when($filter->userIds !== [], fn (Builder $query): Builder => $query->whereIn('id', $filter->userIds))
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    /**
     * One row per member of the population, with every figure the lists sort on.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(PlayerStatsFilter $filter): Collection
    {
        $table = ExpectedWinRate::table();
        $members = $this->population($filter)->get();
        $ids = $members->pluck('id')->all();
        $interclubLines = $this->interclubLines($ids)->groupBy('user_id');
        $tournamentLines = $this->tournamentLines($ids)->groupBy('user_id');

        $inPeriod = $this->periodFor($filter);
        $rosters = DB::table('team_user')->whereIn('user_id', $ids)->get(['user_id', 'team_id'])->groupBy('user_id');
        $fixtures = $this->pastFixtures($rosters->flatten()->pluck('team_id')->unique()->all())->filter($inPeriod);
        $answers = $this->availabilityAnswers($ids)->filter($inPeriod)->groupBy('user_id');
        $reference = $filter->seasonId > 0 ? Season::find($filter->seasonId) : Season::current();
        $seasons = [$reference?->id, $reference?->previous()?->id];

        if ($filter->teamId !== null && $filter->onlyTeamMatches) {
            $teamId = $filter->teamId;
            $interclubLines = $interclubLines->map(fn (Collection $lines): Collection => $lines->filter(
                fn (object $line): bool => (int) $line->visited_team_id === $teamId || (int) $line->visiting_team_id === $teamId,
            ));
        }

        return $members->map(function (User $member) use ($interclubLines, $tournamentLines, $table, $inPeriod, $rosters, $fixtures, $answers, $seasons): array {
            $allInterclub = $interclubLines->get($member->id, collect());
            $allTournaments = $tournamentLines->get($member->id, collect());
            $interclub = $allInterclub->filter($inPeriod)->values();
            $tournaments = $allTournaments->filter($inPeriod)->values();

            return [
                'user_id' => $member->id,
                'name' => trim($member->first_name . ' ' . $member->last_name),
                'ranking' => $member->ranking?->value,
                'interclub' => [
                    ...$this->tally($interclub, $table),
                    ...$this->participation($interclub, $rosters->get($member->id, collect())->pluck('team_id')->all(), $fixtures),
                    'available_not_selected' => $this->leftOut($answers->get($member->id)),
                    'trend' => $this->trend($allInterclub, $table, $seasons),
                ],
                'tournaments' => [
                    ...$this->tally($tournaments, $table),
                    'tournaments' => $tournaments->pluck('tournament_key')->unique()->count(),
                    'trend' => $this->trend($allTournaments, $table, $seasons),
                ],
                'both' => [
                    ...$this->tally($interclub->concat($tournaments), $table),
                    'trend' => $this->trend($allInterclub->concat($allTournaments), $table, $seasons),
                ],
            ];
        })->values();
    }

    /**
     * Every answer a member gave to a fixture already played.
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, object>
     */
    private function availabilityAnswers(array $userIds): Collection
    {
        return DB::table('interclub_user')
            ->join('interclubs', 'interclubs.id', '=', 'interclub_user.interclub_id')
            ->whereIn('interclub_user.user_id', $userIds)
            ->whereNotNull('interclub_user.availability')
            ->where('interclubs.start_date_time', '<', now())
            ->get([
                'interclub_user.user_id',
                'interclub_user.availability',
                'interclub_user.is_selected',
                'interclubs.season_id',
                'interclubs.start_date_time as played_at',
            ]);
    }

    /**
     * @param  Collection<int, object>  $played
     * @param  array<int, int>  $table
     * @return array<int, array{played: int, won: int, expected: float}>
     */
    private function byGap(Collection $played, array $table): array
    {
        $byGap = [];

        foreach (range(-ExpectedWinRate::MAX_GAP, ExpectedWinRate::MAX_GAP) as $gap) {
            $byGap[$gap] = ['played' => 0, 'won' => 0, 'expected' => 0.0];
        }

        foreach ($played->whereNotNull('gap') as $line) {
            $gap = max(-ExpectedWinRate::MAX_GAP, min(ExpectedWinRate::MAX_GAP, $line->gap));
            $byGap[$gap]['played']++;
            $byGap[$gap]['won'] += (int) (bool) $line->we_won;
            $byGap[$gap]['expected'] = round($byGap[$gap]['expected'] + ExpectedWinRate::probability($table, $gap), 2);
        }

        return $byGap;
    }

    /**
     * Interclub lines by the team of ours that played the tie.
     *
     * Asked of the fixture, not of the roster: a player standing in for
     * another team played for that team on the evening.
     *
     * @param  Collection<int, object>  $played
     * @param  array<int, int>  $table
     * @return array<int, array<string, mixed>>
     */
    private function byTeam(Collection $played, array $table): array
    {
        $teams = Team::with('club')
            ->whereIn('id', $played->pluck('visited_team_id')->concat($played->pluck('visiting_team_id'))->filter()->unique())
            ->get()
            ->keyBy('id');

        $ourTeam = function (object $line) use ($teams): ?Team {
            $home = $teams->get((int) $line->visited_team_id);

            return $home?->club?->is_own_club ? $home : $teams->get((int) $line->visiting_team_id);
        };

        return $played
            ->groupBy(fn (object $line): string => (string) $ourTeam($line)?->id)
            ->map(fn (Collection $lines, string $teamId): array => [
                'team' => $teams->get((int) $teamId)?->fullName() ?? '—',
                'season_id' => $teams->get((int) $teamId)?->season_id,
                ...$this->tally($lines, $table),
            ])
            ->sortByDesc('season_id')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, object>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function entries(Collection $lines): array
    {
        return $lines
            ->sortByDesc('played_at')
            ->map(fn (object $line): array => [
                'date' => substr((string) $line->played_at, 0, 10),
                'kind' => $line->interclub_id === null ? 'tournament' : 'interclub',
                'context' => $line->tournament_name ?? null,
                'interclub_id' => $line->interclub_id,
                'opponent' => $line->opponent_name ?? '—',
                'opponent_ranking' => $line->opponent_ranking,
                'our_ranking' => $line->our_ranking,
                'gap' => $line->gap,
            ])
            ->values()
            ->all();
    }

    /**
     * Figures for each group of lines, in the order the keys first appear.
     *
     * @param  Collection<int, object>  $lines
     * @param  Closure(object): string  $key
     * @param  array<int, int>  $table
     * @param  array<int|string, string>  $labels
     * @return array<int, array<string, mixed>>
     */
    private function grouped(Collection $lines, Closure $key, array $table, array $labels = []): array
    {
        return $lines
            ->sortBy('played_at')
            ->groupBy($key)
            ->map(fn (Collection $group, string $label): array => [
                'label' => $labels[$label] ?? $label,
                ...$this->tally($group, $table),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $userIds
     * @return Collection<int, object>
     */
    private function interclubLines(array $userIds): Collection
    {
        return DB::table('interclub_individual_matches as line')
            ->join('interclubs', 'interclubs.id', '=', 'line.interclub_id')
            ->whereIn('line.user_id', $userIds)
            ->where('line.is_double', false)
            ->select([
                'line.user_id',
                'line.interclub_id',
                'line.our_player_ranking as our_ranking',
                'line.opponent_ranking',
                'line.opponent_name',
                'line.opponent_licence',
                'line.we_won',
                'line.is_forfeit',
                'interclubs.season_id',
                'interclubs.visited_team_id',
                'interclubs.visiting_team_id',
                'interclubs.start_date_time as played_at',
            ])
            ->get();
    }

    /**
     * How many times a member said they were available and was not lined up.
     *
     * Null rather than zero for a member with no answer on record: the seasons
     * imported from the federation predate the answers, and "never left out"
     * is not what they say.
     *
     * @param  Collection<int, object>|null  $answers
     */
    private function leftOut(?Collection $answers): ?int
    {
        if ($answers === null || $answers->isEmpty()) {
            return null;
        }

        return $answers->filter(fn (object $answer): bool => $answer->availability === 'available' && ! $answer->is_selected)->count();
    }

    /**
     * The opponents met most, and how each meeting went.
     *
     * Keyed by licence where the sheet gives one: two players may share a name,
     * a licence is the federation's own handle on who somebody is.
     *
     * @param  Collection<int, object>  $played
     * @return array<int, array{name: string, played: int, won: int, ranking: string|null}>
     */
    private function opponents(Collection $played): array
    {
        return $played
            ->filter(fn (object $line): bool => ($line->opponent_licence ?? $line->opponent_name) !== null)
            ->groupBy(fn (object $line): string => (string) ($line->opponent_licence ?? $line->opponent_name))
            ->map(fn (Collection $meetings): array => [
                'name' => (string) $meetings->last()->opponent_name,
                'played' => $meetings->count(),
                'won' => $meetings->filter(fn (object $line): bool => (bool) $line->we_won)->count(),
                'ranking' => $meetings->last()->opponent_ranking,
            ])
            ->sortBy([['played', 'desc'], ['won', 'desc']])
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * Ties played, and the share of their own team's ties a member turned out for.
     *
     * A tie played for another team — standing in — counts in `ties` but not in
     * the share, which would otherwise climb past 100 %.
     *
     * @param  Collection<int, object>  $lines
     * @param  array<int, int>  $teamIds
     * @param  Collection<int, object>  $fixtures
     * @return array{ties: int, team_ties: int, participation: int|null}
     */
    private function participation(Collection $lines, array $teamIds, Collection $fixtures): array
    {
        $teamFixtures = $fixtures->filter(fn (object $fixture): bool => in_array((int) $fixture->visited_team_id, $teamIds, true)
            || in_array((int) $fixture->visiting_team_id, $teamIds, true))->pluck('id');

        $played = $lines->pluck('interclub_id')->unique();
        $teamTies = $teamFixtures->count();

        return [
            'ties' => $played->count(),
            'team_ties' => $teamTies,
            'participation' => $teamTies > 0 ? (int) round($played->intersect($teamFixtures)->count() / $teamTies * 100) : null,
        ];
    }

    /**
     * The fixtures of these teams that were actually played: no bye, no
     * forfeit, and in the past.
     *
     * @param  array<int, int>  $teamIds
     * @return Collection<int, object>
     */
    private function pastFixtures(array $teamIds): Collection
    {
        return DB::table('interclubs')
            ->where(fn ($query) => $query->whereIn('visited_team_id', $teamIds)->orWhereIn('visiting_team_id', $teamIds))
            ->where('is_bye', false)
            ->whereNull('forfeit')
            ->where('start_date_time', '<', now())
            ->get(['id', 'season_id', 'visited_team_id', 'visiting_team_id', 'start_date_time as played_at']);
    }

    /**
     * Whether a line falls in the stretch of time the filter asks for.
     *
     * Lines are always loaded whole and narrowed here: the trends compare the
     * period with what came before it, which a query bound to the period could
     * not see.
     *
     * @return Closure(object): bool
     */
    private function periodFor(PlayerStatsFilter $filter): Closure
    {
        if ($filter->months !== null) {
            $since = now()->subMonths($filter->months)->toDateTimeString();

            return static fn (object $line): bool => (string) $line->played_at >= $since;
        }

        if ($filter->seasonId === 0) {
            return static fn (): bool => true;
        }

        $seasonId = $filter->seasonId ?? Season::current()?->id;

        return static fn (object $line): bool => (int) $line->season_id === $seasonId;
    }

    /**
     * Every change in the ranking a player carried onto a sheet, oldest first.
     *
     * @param  Collection<int, object>  $lines  sorted oldest first
     * @return array<int, array{date: string, ranking: string}>
     */
    private function rankingHistory(Collection $lines): array
    {
        $history = [];
        $last = null;

        foreach ($lines as $line) {
            if ($line->our_ranking === null || $line->our_ranking === $last) {
                continue;
            }

            $history[] = ['date' => substr((string) $line->played_at, 0, 10), 'ranking' => $line->our_ranking];
            $last = $line->our_ranking;
        }

        return $history;
    }

    /**
     * @param  Collection<int, object>  $lines
     * @param  array<int, int>  $table
     * @return array<string, int|float|null>
     */
    private function tally(Collection $lines, array $table): array
    {
        $played = $lines->filter(fn (object $line): bool => ! $line->is_forfeit);
        $won = $played->filter(fn (object $line): bool => (bool) $line->we_won)->count();
        $expected = 0.0;
        $ratedWon = 0;
        $rated = 0;
        $perfs = 0;
        $contrePerfs = 0;

        foreach ($played as $line) {
            $gap = RankingScale::gap($line->our_ranking, $line->opponent_ranking);

            if ($gap === null) {
                continue;
            }

            $rated++;
            $expected += ExpectedWinRate::probability($table, $gap);
            $ratedWon += (int) (bool) $line->we_won;
            $perfs += (int) ($gap > 0 && $line->we_won);
            $contrePerfs += (int) ($gap < 0 && ! $line->we_won);
        }

        $score = round($ratedWon - $expected, 2);

        return [
            'played' => $played->count(),
            'won' => $won,
            'forfeits' => $lines->count() - $played->count(),
            'rate' => $played->count() > 0 ? (int) round($won / $played->count() * 100) : 0,
            'expected' => round($expected, 2),
            'score' => $score,
            'rated' => $rated,
            'score_per_match' => $rated > 0 ? round($score / $rated, 2) : null,
            'perfs' => $perfs,
            'contre_perfs' => $contrePerfs,
        ];
    }

    /**
     * Tournament lines in the interclub lines' shape: no forfeit on record, and
     * a tournament known only by its name on its day.
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, object>
     */
    private function tournamentLines(array $userIds): Collection
    {
        return DB::table('official_tournament_matches')
            ->whereIn('user_id', $userIds)
            ->select([
                'user_id',
                'player_ranking as our_ranking',
                'opponent_ranking',
                'opponent_name',
                'opponent_licence',
                'we_won',
                'season_id',
                'played_on as played_at',
                'tournament_name',
            ])
            ->get()
            ->each(function (object $line): void {
                $line->is_forfeit = false;
                $line->interclub_id = null;
                $line->tournament_key = substr((string) $line->played_at, 0, 10) . '|' . $line->tournament_name;
            });
    }

    /**
     * Whether a player is doing better or worse than before, in score per match.
     *
     * Two readings side by side, because each fails where the other holds: a
     * season against the previous one is steady but slow, the last ten matches
     * against the ten before react at once but feel every lucky evening.
     *
     * @param  Collection<int, object>  $lines  every line of the member's, whatever the period
     * @param  array<int, int>  $table
     * @param  array{0: int|null, 1: int|null}  $seasons  the reference season and the one before it
     * @return array{season: float|null, form: float|null}
     */
    private function trend(Collection $lines, array $table, array $seasons): array
    {
        $played = $lines->filter(fn (object $line): bool => ! $line->is_forfeit);

        [$current, $previous] = array_map(
            fn (?int $seasonId): ?float => $seasonId === null ? null : $this->tally(
                $played->filter(fn (object $line): bool => (int) $line->season_id === $seasonId), $table,
            )['score_per_match'],
            $seasons,
        );

        $rated = $played
            ->filter(fn (object $line): bool => RankingScale::gap($line->our_ranking, $line->opponent_ranking) !== null)
            ->sortByDesc('played_at')
            ->values();

        $form = null;

        if ($rated->count() >= self::FORM_WINDOW * 2) {
            $recent = $this->tally($rated->take(self::FORM_WINDOW), $table)['score_per_match'];
            $before = $this->tally($rated->slice(self::FORM_WINDOW, self::FORM_WINDOW), $table)['score_per_match'];
            $form = round($recent - $before, 2);
        }

        return [
            'season' => $current === null || $previous === null ? null : round($current - $previous, 2),
            'form' => $form,
        ];
    }
}
