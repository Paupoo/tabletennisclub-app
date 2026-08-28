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
     * Calculate standings for a pool
     *
     * @param  Pool  $pool  The pool to calculate standings for
     * @return Collection Collection of players with their stats
     */
    public function calculatePoolStandings(Pool $pool): Collection
    {
        $pool->loadMissing('tournament');

        if ($pool->tournament->match_type === 'double') {
            return $this->calculateDoublesPoolStandings($pool);
        }

        $pool->loadMissing('users');
        $players = $pool->users;
        $matches = TournamentMatch::where('pool_id', $pool->id)->with('sets')->get();

        $noShowIds = TournamentRegistration::where('tournament_id', $pool->tournament_id)
            ->whereIn('user_id', $players->pluck('id'))
            ->where('registration_status', 'no_show')
            ->pluck('user_id')
            ->flip();

        $standings = $players->map(function (User $player) use ($matches, $noShowIds): array {
            $playerMatches = $matches->filter(fn (TournamentMatch $match): bool => $match->player1_id === $player->id || $match->player2_id === $player->id);

            $matchesWon = $playerMatches->where('winner_id', $player->id)->count();

            $setsWon = 0;
            $totalPoints = 0;

            foreach ($playerMatches as $match) {
                if ($match->isCompleted() && ! $match->is_forfeit) {
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
                'no_show' => isset($noShowIds[$player->id]),
            ];
        });

        // Sort standings: no-show players always last, then by wins/sets/points
        return $standings->sortByDesc(function (array $item): int {
            $noShowPenalty = $item['no_show'] ? -1000000 : 0;

            return $noShowPenalty + (int) sprintf('%06d%06d%06d', $item['matches_won'], $item['sets_won'], $item['total_points']);
        })->values();
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
     * Returns a human-readable conflict message if any player or the referee of $match
     * is already involved in another in-progress match of the same tournament, null otherwise.
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

        if ($match->pair1_id) {
            $incomingIds = collect([
                $match->pair1?->player1_id,
                $match->pair1?->player2_id,
                $match->pair2?->player1_id,
                $match->pair2?->player2_id,
                $match->referee_id,
            ])->filter();
        } else {
            $incomingIds = collect([$match->player1_id, $match->player2_id, $match->referee_id])->filter();
        }

        $conflicts = $incomingIds->intersect($busyIds);

        if ($conflicts->isEmpty()) {
            return null;
        }

        $names = $conflicts->map(fn (int $id) => User::find($id)?->full_name ?? "#{$id}")->join(', ');

        return __('Conflict: :names already in an active match.', ['names' => $names]);
    }

    /**
     * Forfeit all remaining scheduled pool matches for a player (e.g. after marking them no-show).
     * The opponent is automatically set as winner. Returns the number of forfeited matches.
     */
    public function forfeitPoolMatchesForPlayer(Tournament $tournament, User $user): int
    {
        $matches = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('pool_id')
            ->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($user): void {
                $q->where('player1_id', $user->id)
                    ->orWhere('player2_id', $user->id);
            })
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->get();

        foreach ($matches as $match) {
            $opponentId = $match->player1_id === $user->id
                ? $match->player2_id
                : $match->player1_id;

            $match->update([
                'status' => 'completed',
                'winner_id' => $opponentId,
                'is_forfeit' => true,
            ]);
        }

        return $matches->count();
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

    private function calculateDoublesPoolStandings(Pool $pool): Collection
    {
        $pool->loadMissing(['pairs.player1', 'pairs.player2']);
        $pairs = $pool->pairs;
        $matches = TournamentMatch::where('pool_id', $pool->id)->with('sets')->get();

        $standings = $pairs->map(function (TournamentPair $pair) use ($matches): array {
            $proxyId = $pair->player1_id;
            $pairMatches = $matches->filter(
                fn (TournamentMatch $m): bool => $m->pair1_id === $pair->id || $m->pair2_id === $pair->id
            );

            $matchesWon = $pairMatches->where('winner_id', $proxyId)->count();
            $setsWon = 0;
            $totalPoints = 0;

            foreach ($pairMatches as $match) {
                if ($match->isCompleted()) {
                    $setsWon += $match->getSetsWon($proxyId);
                    $totalPoints += $match->getTotalPoints($proxyId);
                }
            }

            return [
                'player' => $pair->player1,
                'pair' => $pair,
                'matches_played' => $pairMatches->where('status', 'completed')->count(),
                'matches_won' => $matchesWon,
                'sets_won' => $setsWon,
                'total_points' => $totalPoints,
            ];
        });

        return $standings->sortByDesc(fn (array $item): string => sprintf('%06d%06d%06d', $item['matches_won'], $item['sets_won'], $item['total_points']))->values();
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
}
