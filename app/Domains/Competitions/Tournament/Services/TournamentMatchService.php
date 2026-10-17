<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Shared\Enums\Ranking;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TournamentMatchService
{
    /**
     * AFTT handicap table: `[receiver ranking][opponent ranking] => points given`.
     *
     * Transcribed from the federal document and left as literal data on
     * purpose. The rungs are not evenly spaced — B0 against C0 is worth 3
     * points where B4 against C4 is worth 2 — so no formula over
     * {@see Ranking} positions reproduces it, and any attempt to derive it
     * would silently change what players receive on the table. Only its
     * completeness is enforced, by the test that checks every ranking appears
     * in both dimensions.
     *
     * @see https://bbw.aftt.be/wp-content/uploads/2014/02/handicaps-M-D.pdf
     *
     * @var array<string, array<string, int>>
     */
    private array $handicapPoints = [
        'B0' => [
            'B0' => 0,
            'B2' => 1,
            'B4' => 2,
            'B6' => 2,
            'C0' => 3,
            'C2' => 3,
            'C4' => 4,
            'C6' => 4,
            'D0' => 5,
            'D2' => 5,
            'D4' => 6,
            'D6' => 6,
            'E0' => 7,
            'E2' => 7,
            'E4' => 8,
            'E6' => 8,
            'NC' => 8,
            'NA' => 8,
        ],
        'B2' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 1,
            'B6' => 2,
            'C0' => 2,
            'C2' => 3,
            'C4' => 3,
            'C6' => 4,
            'D0' => 4,
            'D2' => 5,
            'D4' => 5,
            'D6' => 6,
            'E0' => 6,
            'E2' => 7,
            'E4' => 7,
            'E6' => 8,
            'NC' => 8,
            'NA' => 8,
        ],
        'B4' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 1,
            'C0' => 2,
            'C2' => 2,
            'C4' => 3,
            'C6' => 3,
            'D0' => 4,
            'D2' => 4,
            'D4' => 5,
            'D6' => 5,
            'E0' => 6,
            'E2' => 6,
            'E4' => 7,
            'E6' => 7,
            'NC' => 8,
            'NA' => 8,
        ],
        'B6' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 0,
            'C0' => 1,
            'C2' => 2,
            'C4' => 2,
            'C6' => 3,
            'D0' => 3,
            'D2' => 4,
            'D4' => 4,
            'D6' => 5,
            'E0' => 5,
            'E2' => 6,
            'E4' => 6,
            'E6' => 7,
            'NC' => 7,
            'NA' => 7,
        ],
        'C0' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 0,
            'C0' => 0,
            'C2' => 1,
            'C4' => 2,
            'C6' => 2,
            'D0' => 3,
            'D2' => 3,
            'D4' => 4,
            'D6' => 4,
            'E0' => 5,
            'E2' => 5,
            'E4' => 6,
            'E6' => 6,
            'NC' => 7,
            'NA' => 7,
        ],
        'C2' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 0,
            'C0' => 0,
            'C2' => 0,
            'C4' => 1,
            'C6' => 2,
            'D0' => 2,
            'D2' => 3,
            'D4' => 3,
            'D6' => 4,
            'E0' => 4,
            'E2' => 5,
            'E4' => 5,
            'E6' => 6,
            'NC' => 6,
            'NA' => 6,
        ],
        'C4' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 0,
            'C0' => 0,
            'C2' => 0,
            'C4' => 0,
            'C6' => 1,
            'D0' => 2,
            'D2' => 2,
            'D4' => 3,
            'D6' => 3,
            'E0' => 4,
            'E2' => 4,
            'E4' => 5,
            'E6' => 5,
            'NC' => 6,
            'NA' => 6,
        ],
        'C6' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 0,
            'C0' => 0,
            'C2' => 0,
            'C4' => 0,
            'C6' => 0,
            'D0' => 1,
            'D2' => 2,
            'D4' => 2,
            'D6' => 3,
            'E0' => 3,
            'E2' => 4,
            'E4' => 4,
            'E6' => 5,
            'NC' => 5,
            'NA' => 5,
        ],
        'D0' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 0,
            'C0' => 0,
            'C2' => 0,
            'C4' => 0,
            'C6' => 0,
            'D0' => 0,
            'D2' => 1,
            'D4' => 2,
            'D6' => 2,
            'E0' => 3,
            'E2' => 3,
            'E4' => 4,
            'E6' => 4,
            'NC' => 5,
            'NA' => 5,
        ],
        'D2' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 0,
            'C0' => 0,
            'C2' => 0,
            'C4' => 0,
            'C6' => 0,
            'D0' => 0,
            'D2' => 0,
            'D4' => 1,
            'D6' => 2,
            'E0' => 2,
            'E2' => 3,
            'E4' => 3,
            'E6' => 4,
            'NC' => 4,
            'NA' => 4,
        ],
        'D4' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 0,
            'C0' => 0,
            'C2' => 0,
            'C4' => 0,
            'C6' => 0,
            'D0' => 0,
            'D2' => 0,
            'D4' => 0,
            'D6' => 1,
            'E0' => 2,
            'E2' => 2,
            'E4' => 3,
            'E6' => 3,
            'NC' => 4,
            'NA' => 4,
        ],
        'D6' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 0,
            'C0' => 0,
            'C2' => 0,
            'C4' => 0,
            'C6' => 0,
            'D0' => 0,
            'D2' => 0,
            'D4' => 0,
            'D6' => 0,
            'E0' => 1,
            'E2' => 2,
            'E4' => 2,
            'E6' => 3,
            'NC' => 3,
            'NA' => 3,
        ],
        'E0' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 0,
            'C0' => 0,
            'C2' => 0,
            'C4' => 0,
            'C6' => 0,
            'D0' => 0,
            'D2' => 0,
            'D4' => 0,
            'D6' => 0,
            'E0' => 0,
            'E2' => 1,
            'E4' => 2,
            'E6' => 2,
            'NC' => 3,
            'NA' => 3,
        ],
        'E2' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 0,
            'C0' => 0,
            'C2' => 0,
            'C4' => 0,
            'C6' => 0,
            'D0' => 0,
            'D2' => 0,
            'D4' => 0,
            'D6' => 0,
            'E0' => 0,
            'E2' => 0,
            'E4' => 1,
            'E6' => 2,
            'NC' => 2,
            'NA' => 2,
        ],
        'E4' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 0,
            'C0' => 0,
            'C2' => 0,
            'C4' => 0,
            'C6' => 0,
            'D0' => 0,
            'D2' => 0,
            'D4' => 0,
            'D6' => 0,
            'E0' => 0,
            'E2' => 0,
            'E4' => 0,
            'E6' => 1,
            'NC' => 2,
            'NA' => 2,
        ],
        'E6' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 0,
            'C0' => 0,
            'C2' => 0,
            'C4' => 0,
            'C6' => 0,
            'D0' => 0,
            'D2' => 0,
            'D4' => 0,
            'D6' => 0,
            'E0' => 0,
            'E2' => 0,
            'E4' => 0,
            'E6' => 0,
            'NC' => 1,
            'NA' => 1,
        ],
        'NC' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 0,
            'C0' => 0,
            'C2' => 0,
            'C4' => 0,
            'C6' => 0,
            'D0' => 0,
            'D2' => 0,
            'D4' => 0,
            'D6' => 0,
            'E0' => 0,
            'E2' => 0,
            'E4' => 0,
            'E6' => 0,
            'NC' => 0,
            'NA' => 0,
        ],
        'NA' => [
            'B0' => 0,
            'B2' => 0,
            'B4' => 0,
            'B6' => 0,
            'C0' => 0,
            'C2' => 0,
            'C4' => 0,
            'C6' => 0,
            'D0' => 0,
            'D2' => 0,
            'D4' => 0,
            'D6' => 0,
            'E0' => 0,
            'E2' => 0,
            'E4' => 0,
            'E6' => 0,
            'NC' => 0,
            'NA' => 0,
        ],
    ];

    /**
     * A ratio that reads 1 when nothing was conceded and 0 when nothing was won.
     */
    public static function ratio(int $won, int $lost): float
    {
        return $won + $lost === 0 ? 0.0 : $won / ($won + $lost);
    }

    /**
     * A draw that gives the same answer every time it is asked.
     */
    public static function stableDraw(int $tournamentId, int $key): int
    {
        return crc32($tournamentId . '-' . $key);
    }

    /**
     * Assign referees to all matches in a pool using a min-count algorithm.
     * For each match (in match_order), pick the eligible player (not playing) with
     * the fewest prior referee assignments. Ties are broken randomly.
     * Supports both singles (pool_user) and doubles (pool_pair — all individual
     * players from every pair are candidates).
     */
    public function assignRefereesToPool(Pool $pool): void
    {
        $pool->loadMissing(['tournament', 'users', 'pairs.player1', 'pairs.player2']);

        $matches = TournamentMatch::where('pool_id', $pool->id)
            ->orderBy('match_order')
            ->with(['pair1', 'pair2'])
            ->get();

        if ($matches->isEmpty()) {
            return;
        }

        $isDoubles = $pool->tournament->match_type === 'double';

        if ($isDoubles) {
            // Build candidate list from all individual players in the pool's pairs
            $allPlayerIds = $pool->pairs->flatMap(
                fn (TournamentPair $pair): array => array_filter([$pair->player1_id, $pair->player2_id])
            )->unique()->values()->toArray();
        } else {
            $allPlayerIds = $pool->users->pluck('id')->toArray();
        }

        if (empty($allPlayerIds)) {
            return;
        }

        /** @var array<int, int> player_id => referee assignment count */
        $refereeCount = array_fill_keys($allPlayerIds, 0);

        foreach ($matches as $match) {
            if ($isDoubles) {
                $playing = array_filter([
                    $match->pair1?->player1_id,
                    $match->pair1?->player2_id,
                    $match->pair2?->player1_id,
                    $match->pair2?->player2_id,
                ]);
            } else {
                if ($match->player1_id === null || $match->player2_id === null) {
                    continue;
                }
                $playing = [$match->player1_id, $match->player2_id];
            }

            $eligible = array_keys(array_filter(
                $refereeCount,
                fn (int $count, int $id): bool => ! in_array($id, $playing, true),
                ARRAY_FILTER_USE_BOTH
            ));

            if ($eligible === []) {
                continue;
            }

            $minCount = min(array_map(fn (int $id): int => $refereeCount[$id], $eligible));
            $candidates = array_values(array_filter($eligible, fn (int $id): bool => $refereeCount[$id] === $minCount));
            $refereeId = $candidates[random_int(0, count($candidates) - 1)];

            $match->update(['referee_id' => $refereeId]);
            $refereeCount[$refereeId]++;
        }
    }

    /**
     * Doubles handicap: average of the 4 individual cross-handicaps, rounded up.
     * Returns ['pair1_handicap' => int, 'pair2_handicap' => int].
     * The handicap is assigned to the weaker pair (higher handicap = more points given).
     *
     * Example — Pair A: Alice (B2) + Bob (C4) vs Pair B: Clara (B4) + David (D0)
     *   A1vsB1(AlicevClara)=1, A1vsB2(AlicevDavid)=4, A2vsB1(BobvClara)=3, A2vsB2(BobvDavid)=5
     *   total=13, avg=13/4=3.25 → ceil=4 pts to Pair B (weaker)
     *
     * @return array{pair1_handicap: int, pair2_handicap: int}
     */
    public function calculateDoublesHandicap(TournamentPair $pair1, TournamentPair $pair2): array
    {
        $p1a = $pair1->player1;
        $p1b = $pair1->player2;
        $p2a = $pair2->player1;
        $p2b = $pair2->player2;

        if (! $p1a || ! $p1b || ! $p2a || ! $p2b) {
            return ['pair1_handicap' => 0, 'pair2_handicap' => 0];
        }

        $valid = $this->isValidRanking($p1a->ranking->value)
            && $this->isValidRanking($p1b->ranking->value)
            && $this->isValidRanking($p2a->ranking->value)
            && $this->isValidRanking($p2b->ranking->value);

        if (! $valid) {
            return ['pair1_handicap' => 0, 'pair2_handicap' => 0];
        }

        // Sum of the 4 individual handicaps (pair1 member vs pair2 member, weaker receives points)
        $h11 = $this->calculateHandicapPointsToReceive($p1a, $p2a); // A1 vs B1
        $h12 = $this->calculateHandicapPointsToReceive($p1a, $p2b); // A1 vs B2
        $h21 = $this->calculateHandicapPointsToReceive($p1b, $p2a); // A2 vs B1
        $h22 = $this->calculateHandicapPointsToReceive($p1b, $p2b); // A2 vs B2

        $totalForPair1 = $h11 + $h12 + $h21 + $h22;

        $h11r = $this->calculateHandicapPointsToReceive($p2a, $p1a); // B1 vs A1
        $h12r = $this->calculateHandicapPointsToReceive($p2a, $p1b); // B1 vs A2
        $h21r = $this->calculateHandicapPointsToReceive($p2b, $p1a); // B2 vs A1
        $h22r = $this->calculateHandicapPointsToReceive($p2b, $p1b); // B2 vs A2

        $totalForPair2 = $h11r + $h12r + $h21r + $h22r;

        // The weaker pair receives the ceil average; stronger pair receives 0
        $pair1Hcp = (int) ceil($totalForPair1 / 4);
        $pair2Hcp = (int) ceil($totalForPair2 / 4);

        // Only assign handicap to the weaker pair (higher value), zero out the stronger
        if ($pair1Hcp >= $pair2Hcp) {
            return ['pair1_handicap' => $pair1Hcp, 'pair2_handicap' => 0];
        }

        return ['pair1_handicap' => 0, 'pair2_handicap' => $pair2Hcp];
    }

    /**
     * The standings of a pool, the way the championship breaks its ties.
     *
     * Wins first. Two entrants level on wins are settled by the match they
     * played each other; three or more by the sets they won against one
     * another, then the points, counted between them only. A forfeit counts
     * as a win but never in sets or points. A withdrawn entrant ends last.
     * What is still level after all that is ordered by a draw that stays the
     * same from one reading to the next, so a polled page never shuffles.
     *
     * A doubles entrant is a pair, read through its first player: the matches
     * carry that player's id on each side.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function calculatePoolStandings(Pool $pool): Collection
    {
        $pool->loadMissing('tournament');
        $isDoubles = $pool->tournament->match_type === 'double';
        $matches = TournamentMatch::where('pool_id', $pool->id)->with('sets')->get();

        $absentIds = TournamentRegistration::where('tournament_id', $pool->tournament_id)
            ->where('registration_status', 'no_show')
            ->pluck('user_id')
            ->flip();

        if ($isDoubles) {
            $pool->loadMissing(['pairs.player1', 'pairs.player2']);
            $entrants = $pool->pairs->map(fn (TournamentPair $pair): array => [
                'key' => $pair->player1_id,
                'player' => $pair->player1,
                'pair' => $pair,
                'no_show' => isset($absentIds[$pair->player1_id]) || isset($absentIds[$pair->player2_id]),
            ]);
        } else {
            $pool->loadMissing('users');
            $entrants = $pool->users->map(fn (User $player): array => [
                'key' => $player->id,
                'player' => $player,
                'no_show' => isset($absentIds[$player->id]),
            ]);
        }

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = $entrants->map(fn (array $entrant): array => $entrant + $this->recordAgainst($entrant['key'], $matches));

        return $this->rankByChampionshipRules($rows, $matches, $pool->tournament_id)
            ->map(function (array $row): array {
                unset($row['key']);

                return $row;
            })
            ->values();
    }

    /**
     * Calculate standings for a pool
     *
     * @param  Tournament  $tournament  The tournament to calculate pools standings for
     * @return Collection Collection of players with their stats
     */
    public function calculateRowStandings(Tournament $tournament): Collection
    {
        $tournament->loadMissing('users');
        $players = $tournament->users;
        $matches = TournamentMatch::where('tournament_id', $tournament->id)->get();

        $standings = $players->map(function (User $player) use ($matches): array {
            $playerMatches = $matches->filter(fn (TournamentMatch $match): bool => $match->player1_id === $player->id || $match->player2_id === $player->id);

            $matchesWon = $playerMatches->where('winner_id', $player->id)->count();

            $setsWon = 0;
            $totalPoints = 0;

            foreach ($playerMatches as $match) {
                if ($match->isCompleted()) {
                    $setsWon += $match->getSetsWon($player->id);
                    $totalPoints += $match->getTotalPoints($player->id);
                }
            }

            return [
                'player' => $player,
                'matches_played' => $playerMatches->where('status', 'completed')->count(),
                'matches_won' => $matchesWon,
                'sets_won' => $setsWon,
                'total_points' => $totalPoints,
            ];
        });

        // Sort standings by matches won (desc), sets won (desc), and total points (desc)
        return $standings->sortByDesc([
            ['matches_won', 'desc'],
            ['sets_won', 'desc'],
            ['total_points', 'desc'],
        ])->values();
    }

    /**
     * Returns a human-readable conflict message if a player of $match is already
     * involved in another in-progress match of the same tournament — playing or
     * refereeing — null otherwise. The referee of $match never blocks it.
     * Handles both singles (player1/2_id) and doubles (all 4 pair members).
     */
    public function detectStartConflict(Tournament $tournament, TournamentMatch $match): ?string
    {
        $activeMatches = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('status', 'in_progress')
            ->whereExists(fn (Builder $q) => $q
                ->from('table_tournament')
                ->whereColumn('table_tournament.tournament_match_id', 'tournament_matches.id')
                ->where('table_tournament.is_table_free', false)
            )
            ->with(['pair1', 'pair2'])
            ->get();

        $busyIds = collect();
        foreach ($activeMatches as $active) {
            if ($active->pair1_id) {
                $busyIds->push($active->pair1?->player1_id, $active->pair1?->player2_id);
                $busyIds->push($active->pair2?->player1_id, $active->pair2?->player2_id);
            } else {
                $busyIds->push($active->player1_id, $active->player2_id);
            }
            if ($active->referee_id) {
                $busyIds->push($active->referee_id);
            }
        }
        $busyIds = $busyIds->filter()->unique()->values();

        // The incoming referee is a suggestion, not a lock: a busy one is
        // replaced in the launch drawer, or the players count themselves.
        $match->loadMissing(['pair1', 'pair2']);
        $incomingIds = $match->sidePlayerIds(1)->merge($match->sidePlayerIds(2));

        $conflicts = $incomingIds->intersect($busyIds);

        if ($conflicts->isEmpty()) {
            return null;
        }

        $names = $conflicts->map(fn (int $id) => User::find($id)?->full_name ?? "#{$id}")->join(', ');

        return __('Conflict: :names already in an active match.', ['names' => $names]);
    }

    /**
     * One side did not play: the other wins, and the table is free again.
     *
     * A forfeit counts as a win in the standings, never in sets or points.
     *
     * @param  1|2  $absentSide
     */
    public function forfeit(TournamentMatch $match, int $absentSide): void
    {
        $winnerId = $absentSide === 1 ? $match->player2_id : $match->player1_id;

        DB::transaction(function () use ($match, $winnerId): void {
            $match->sets()->delete();
            $match->update(['status' => 'completed', 'winner_id' => $winnerId, 'is_forfeit' => true]);

            DB::table('table_tournament')
                ->where('tournament_id', $match->tournament_id)
                ->where('tournament_match_id', $match->id)
                ->where('is_table_free', false)
                ->update(['is_table_free' => true, 'match_ended_at' => now()]);
        });
    }

    /**
     * A player withdraws: every match their side still has to play is lost.
     *
     * The matches already played stand. In doubles the pair goes with them.
     * A bracket match whose other side is not known yet is left for the
     * winner of the previous round to collect.
     *
     * @return Collection<int, TournamentMatch> the matches forfeited
     */
    public function forfeitRemainingMatches(Tournament $tournament, User $user): Collection
    {
        $forfeited = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->with(['pair1', 'pair2'])
            ->get()
            ->filter(fn (TournamentMatch $match): bool => $match->sidePlayerIds(1)->contains($user->id)
                || $match->sidePlayerIds(2)->contains($user->id))
            ->values();

        foreach ($forfeited as $match) {
            $this->forfeit($match, $match->sidePlayerIds(1)->contains($user->id) ? 1 : 2);
        }

        return $forfeited;
    }

    /**
     * Generate all matches for a pool using Round Robin algorithm
     *
     * @param  Pool  $pool  The pool to generate matches for
     * @return Collection The generated matches
     */
    public function generateMatches(Pool $pool): Collection
    {
        $pool->loadMissing(['tournament']);

        if ($pool->tournament->match_type === 'double') {
            return $this->generateDoublesMatches($pool);
        }

        $pool->loadMissing(['users']);
        $players = $pool->users->toArray();
        $numberOfPlayers = count($players);

        // If odd number of players, add a "dummy" player (bye)
        $hasDummy = false;
        if ($numberOfPlayers % 2 !== 0) {
            $players[] = ['id' => null, 'name' => 'BYE'];
            $hasDummy = true;
            $numberOfPlayers++;
        }

        $rounds = $numberOfPlayers - 1;
        $matchesPerRound = $numberOfPlayers / 2;

        // Create matches array
        $matches = [];
        $matchOrder = 1;

        for ($round = 0; $round < $rounds; $round++) {
            for ($match = 0; $match < $matchesPerRound; $match++) {
                $home = ($round + $match) % ($numberOfPlayers - 1);
                $away = ($numberOfPlayers - 1 - $match + $round) % ($numberOfPlayers - 1);

                // Last player stays in the same position while the others rotate
                if ($match === 0) {
                    $away = $numberOfPlayers - 1;
                }

                // Skip matches with dummy player
                if ($hasDummy && ($players[$home]['id'] === null || $players[$away]['id'] === null)) {
                    continue;
                }

                $matches[] = [
                    'pool_id' => $pool->id,
                    'tournament_id' => $pool->tournament->id,
                    'player1_id' => $players[$home]['id'],
                    'player2_id' => $players[$away]['id'],
                    'player1_handicap_points' => $pool->tournament->has_handicap_points ? $this->calculateHandicapPointsToReceive(User::find($players[$home]['id']), User::find($players[$away]['id'])) : 0,
                    'player2_handicap_points' => $pool->tournament->has_handicap_points ? $this->calculateHandicapPointsToReceive(User::find($players[$away]['id']), User::find($players[$home]['id'])) : 0,
                    'status' => 'scheduled',
                    'match_order' => $matchOrder++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Delete any existing matches for this pool
        TournamentMatch::where('pool_id', $pool->id)->delete();

        // Insert all matches at once
        TournamentMatch::insert($matches);

        return TournamentMatch::where('pool_id', $pool->id)->get();
    }

    /**
     * Generate matches for all pools in a tournament
     *
     * @param  Tournament  $tournament  The tournament
     * @return array Array of pools with their matches
     */
    public function generateTournamentMatches(Tournament $tournament): array
    {
        $tournament->loadMissing(['pools.users', 'pools.tournament']);
        $result = [];

        foreach ($tournament->pools as $pool) {
            $result[$pool->id] = $this->generateMatches($pool);
        }

        return $result;
    }

    /**
     * The starting points of each side of a match, as its tournament rules.
     *
     * Zero on both sides when the tournament plays without handicap, or while a
     * side is still unknown — a bracket match waiting for a winner.
     *
     * @return array{player1: int, player2: int}
     */
    public function handicapFor(TournamentMatch $match): array
    {
        $match->loadMissing(['tournament', 'player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2']);

        if (! $match->tournament?->has_handicap_points || $match->player1 === null || $match->player2 === null) {
            return ['player1' => 0, 'player2' => 0];
        }

        if ($match->pair1 !== null && $match->pair2 !== null) {
            $doubles = $this->calculateDoublesHandicap($match->pair1, $match->pair2);

            return ['player1' => $doubles['pair1_handicap'], 'player2' => $doubles['pair2_handicap']];
        }

        if (! $this->isValidRanking($match->player1->ranking->value) || ! $this->isValidRanking($match->player2->ranking->value)) {
            return ['player1' => 0, 'player2' => 0];
        }

        return [
            'player1' => $this->calculateHandicapPointsToReceive($match->player1, $match->player2),
            'player2' => $this->calculateHandicapPointsToReceive($match->player2, $match->player1),
        ];
    }

    /**
     * Wins, sets and points of one entrant over the given matches.
     *
     * @param  Collection<int, TournamentMatch>  $matches
     * @return array{matches_played: int, matches_won: int, sets_won: int, sets_lost: int, total_points: int, points_lost: int}
     */
    public function recordAgainst(int $key, Collection $matches): array
    {
        $record = ['matches_played' => 0, 'matches_won' => 0, 'sets_won' => 0, 'sets_lost' => 0, 'total_points' => 0, 'points_lost' => 0];

        foreach ($matches as $match) {
            if (! $match->isCompleted() || ! in_array($key, [$match->player1_id, $match->player2_id], true)) {
                continue;
            }

            $record['matches_played']++;
            $record['matches_won'] += $match->winner_id === $key ? 1 : 0;

            if ($match->is_forfeit) {
                continue;
            }

            $opponent = $match->player1_id === $key ? $match->player2_id : $match->player1_id;
            $record['sets_won'] += $match->getSetsWon($key);
            $record['sets_lost'] += $opponent === null ? 0 : $match->getSetsWon($opponent);
            $record['total_points'] += $match->getTotalPoints($key);
            $record['points_lost'] += $opponent === null ? 0 : $match->getTotalPoints($opponent);
        }

        return $record;
    }

    /**
     * Record the score of a match being played — the first entry only.
     *
     * The table's QR code and the live centre can both send it. Whichever
     * arrives first is kept; the other is told the score is already in, and a
     * correction goes through the scorers' table. The row is locked so that
     * two sends at the same instant still make one.
     *
     * @param  array<int, array{player1_score: int, player2_score: int}>  $setResults
     * @return bool whether this entry was the one kept
     */
    public function recordFirstResult(TournamentMatch $match, array $setResults): bool
    {
        $recorded = DB::transaction(function () use ($match, $setResults): bool {
            $current = TournamentMatch::whereKey($match->id)->lockForUpdate()->first();

            if (! $current instanceof TournamentMatch || $current->status !== 'in_progress') {
                return false;
            }

            $current->recordResult($setResults);

            DB::table('table_tournament')
                ->where('tournament_id', $current->tournament_id)
                ->where('tournament_match_id', $current->id)
                ->where('is_table_free', false)
                ->update(['is_table_free' => true, 'match_ended_at' => now()]);

            return true;
        });

        if ($recorded) {
            $match->refresh();

            if ($match->round !== null && $match->winner_id !== null) {
                app(TournamentFinalPhaseService::class)->completeMatch($match, $match->winner_id);
            }
        }

        return $recorded;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $level
     * @param  Collection<int, TournamentMatch>  $matches
     * @return Collection<int, array<string, mixed>>
     */
    private function breakTie(Collection $level, Collection $matches, int $tournamentId): Collection
    {
        if ($level->count() === 1) {
            return $level;
        }

        $keys = $level->pluck('key')->all();
        $between = $matches->filter(fn (TournamentMatch $match): bool => in_array($match->player1_id, $keys, true)
            && in_array($match->player2_id, $keys, true));

        if ($level->count() === 2) {
            $decider = $between->first(fn (TournamentMatch $match): bool => $match->isCompleted() && $match->winner_id !== null);

            if ($decider !== null) {
                return $level->sortByDesc(fn (array $row): bool => $row['key'] === $decider->winner_id)->values();
            }
        }

        return $level
            ->map(fn (array $row): array => ['row' => $row, 'mini' => $this->recordAgainst($row['key'], $between)])
            ->sort(function (array $a, array $b) use ($tournamentId): int {
                return [
                    self::ratio($b['mini']['sets_won'], $b['mini']['sets_lost']),
                    self::ratio($b['mini']['total_points'], $b['mini']['points_lost']),
                    self::ratio($b['row']['sets_won'], $b['row']['sets_lost']),
                    self::ratio($b['row']['total_points'], $b['row']['points_lost']),
                    self::stableDraw($tournamentId, $a['row']['key']),
                ] <=> [
                    self::ratio($a['mini']['sets_won'], $a['mini']['sets_lost']),
                    self::ratio($a['mini']['total_points'], $a['mini']['points_lost']),
                    self::ratio($a['row']['sets_won'], $a['row']['sets_lost']),
                    self::ratio($a['row']['total_points'], $a['row']['points_lost']),
                    self::stableDraw($tournamentId, $b['row']['key']),
                ];
            })
            ->pluck('row')
            ->values();
    }

    /**
     * This function caculates the handicap points to receive for player 1 vs player 2 based on both their ranking.
     * Base on referece document : https://bbw.aftt.be/wp-content/uploads/2014/02/handicaps-M-D.pdf
     */
    private function calculateHandicapPointsToReceive(User $player1, User $player2): int
    {
        if (! $this->isValidRanking($player1->ranking->value) || ! $this->isValidRanking($player2->ranking->value)) {
            throw new InvalidArgumentException('Classement invalide.');
        }

        return $this->handicapPoints[$player2->ranking->value][$player1->ranking->value];
    }

    /**
     * Round-robin match generation for a doubles pool.
     * Uses pair1_id / pair2_id. Sets player1_id / player2_id to each pair's player1
     * so that winner_id and standings logic remain unchanged.
     */
    private function generateDoublesMatches(Pool $pool): Collection
    {
        $pool->loadMissing(['pairs.player1', 'pairs.player2', 'tournament']);
        $pairs = $pool->pairs->values()->toArray();
        $count = count($pairs);

        if ($count < 2) {
            return collect();
        }

        $hasDummy = false;
        if ($count % 2 !== 0) {
            $pairs[] = null;
            $hasDummy = true;
            $count++;
        }

        $rounds = $count - 1;
        $perRound = $count / 2;
        $matches = [];
        $order = 1;

        for ($round = 0; $round < $rounds; $round++) {
            for ($m = 0; $m < $perRound; $m++) {
                $homeIdx = ($round + $m) % ($count - 1);
                $awayIdx = ($count - 1 - $m + $round) % ($count - 1);

                if ($m === 0) {
                    $awayIdx = $count - 1;
                }

                $pair1 = $pairs[$homeIdx];
                $pair2 = $pairs[$awayIdx];

                if ($hasDummy && ($pair1 === null || $pair2 === null)) {
                    continue;
                }

                /** @var TournamentPair $p1 */
                $p1 = TournamentPair::with(['player1', 'player2'])->find($pair1['id']);
                /** @var TournamentPair $p2 */
                $p2 = TournamentPair::with(['player1', 'player2'])->find($pair2['id']);

                $handicap = $pool->tournament->has_handicap_points
                    ? $this->calculateDoublesHandicap($p1, $p2)
                    : ['pair1_handicap' => 0, 'pair2_handicap' => 0];

                $matches[] = [
                    'pool_id' => $pool->id,
                    'tournament_id' => $pool->tournament->id,
                    'pair1_id' => $p1->id,
                    'pair2_id' => $p2->id,
                    // Proxy player IDs so winner_id and scoring logic stay intact
                    'player1_id' => $p1->player1_id,
                    'player2_id' => $p2->player1_id,
                    'player1_handicap_points' => $handicap['pair1_handicap'],
                    'player2_handicap_points' => $handicap['pair2_handicap'],
                    'status' => 'scheduled',
                    'match_order' => $order++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        TournamentMatch::where('pool_id', $pool->id)->delete();
        TournamentMatch::insert($matches);

        return TournamentMatch::where('pool_id', $pool->id)->get();
    }

    /**
     * Verify if the ranking exists
     *
     * @param  string  $value  The ranking to check
     */
    private function isValidRanking(string $value): bool
    {
        return Ranking::tryFrom($value) !== null;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  Collection<int, TournamentMatch>  $matches
     * @return Collection<int, array<string, mixed>>
     */
    private function rankByChampionshipRules(Collection $rows, Collection $matches, int $tournamentId): Collection
    {
        [$withdrawn, $playing] = $rows->partition(fn (array $row): bool => (bool) $row['no_show']);

        $ranked = $playing
            ->groupBy('matches_won')
            ->sortKeysDesc()
            ->flatMap(fn (Collection $level): array => $this->breakTie($level->values(), $matches, $tournamentId)->all());

        $last = $withdrawn->sortByDesc('matches_won')->values();

        return $ranked->concat($last)->values();
    }
}
