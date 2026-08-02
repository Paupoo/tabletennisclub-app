<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Services;

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use Exception;
use Illuminate\Support\Collection;

class TournamentFinalPhaseService
{
    public function __construct(
        private readonly TournamentMatchService $tournamentMatchService,
        private readonly TournamentPoolService $tournamentPoolService,
    ) {}

    /**
     * After a bracket match completes, assign referees to ALL bracket matches
     * that are now ready (both players set, no referee yet). Handles the case
     * where final and bronze become ready simultaneously after the second semi.
     */
    public function assignBracketReferee(TournamentMatch $completedMatch): void
    {
        $unassigned = TournamentMatch::where('tournament_id', $completedMatch->tournament_id)
            ->whereNotNull('round')
            ->whereNotIn('round', ['final', 'bronze'])
            ->where('status', 'scheduled')
            ->whereNull('referee_id')
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->orderBy('match_order')
            ->get();

        foreach ($unassigned as $target) {
            $this->tryAssignBracketReferee($completedMatch->tournament_id, $target);
        }
    }

    /**
     * Assign referees to the initial bracket matches (first round) using pool
     * non-qualifiers as candidates. Distributes assignments evenly.
     */
    public function assignInitialBracketReferees(Tournament $tournament): void
    {
        // All players who participated in pool matches
        $poolPlayerIds = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('pool_id')
            ->get()
            ->flatMap(fn (TournamentMatch $m) => [$m->player1_id, $m->player2_id])
            ->filter()
            ->unique()
            ->values();

        // Players who qualified (present in bracket matches)
        $qualifiedIds = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('round')
            ->whereNotNull('player1_id')
            ->get()
            ->flatMap(fn (TournamentMatch $m) => [$m->player1_id, $m->player2_id])
            ->filter()
            ->unique()
            ->values();

        // Non-qualifiers are the referee candidates
        $candidates = $poolPlayerIds->diff($qualifiedIds)->values();

        if ($candidates->isEmpty()) {
            return;
        }

        $bracketMatches = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('round')
            ->whereNotIn('round', ['final', 'bronze'])
            ->where('status', 'scheduled')
            ->whereNull('referee_id')
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->orderBy('match_order')
            ->get();

        if ($bracketMatches->isEmpty()) {
            return;
        }

        // Track local assignment counts so we distribute evenly within this batch
        $refereeCounts = array_fill_keys($candidates->toArray(), 0);

        foreach ($bracketMatches as $match) {
            $playingIds = collect([$match->player1_id, $match->player2_id])->filter();
            $eligible = $candidates->diff($playingIds)->values();

            if ($eligible->isEmpty()) {
                continue;
            }

            $ranked = $eligible
                ->map(fn (int $id) => ['id' => $id, 'count' => $refereeCounts[$id] ?? 0])
                ->sortBy('count');

            $minCount = $ranked->first()['count'];
            $pool = $ranked->filter(fn (array $c) => $c['count'] === $minCount)->values()->all();
            $chosen = $pool[random_int(0, count($pool) - 1)]['id'];
            $match->update(['referee_id' => $chosen]);
            $refereeCounts[$chosen]++;
        }
    }

    public function checkIfAllPoolsAreFinished(Tournament $tournament): bool
    {
        $result = true;
        foreach ($tournament->pools as $pool) {
            if ($this->tournamentPoolService->isPoolFinished($pool) === false) {
                $result = false;
            }
        }

        return $result;
    }

    public function checkIfAllPoolsContainsMatches(Tournament $tournament): bool
    {
        $result = true;
        foreach ($tournament->pools as $pool) {
            if ($pool->tournamentMatches()->count() === 0) {
                $result = false;
            }
        }

        return $result;
    }

    public function cleanNextMatch(TournamentMatch $match): void
    {
        // If there's a next match with one of our players, reset it
        if ($match->next_match_id) {
            $nextMatch = TournamentMatch::find($match->next_match_id);

            if ($nextMatch) {
                if ($nextMatch->player1_id === $match->player1_id || $nextMatch->player1_id === $match->player2_id) {
                    $nextMatch->update(['player1_id' => null]);
                }

                if ($nextMatch->player2_id === $match->player1_id || $nextMatch->player2_id === $match->player2_id) {
                    $nextMatch->update(['player2_id' => null]);
                }
            }
        }
    }

    /**
     * Update match with winner and progress to next round
     */
    public function completeMatch(TournamentMatch $match, int $winnerId): bool
    {
        $match->update([
            'winner_id' => $winnerId,
            'status' => 'completed',
        ]);

        // If there's a next match with one of our players, reset it
        $this->cleanNextMatch($match);

        // Propagate winner (and their pair for doubles) to the next bracket match
        if ($match->next_match_id) {
            $nextMatch = TournamentMatch::find($match->next_match_id);
            if ($nextMatch) {
                $slot = $nextMatch->player1_id ? 2 : 1;
                $winnerPairId = $match->player1_id === $winnerId ? $match->pair1_id : $match->pair2_id;

                $update = ["player{$slot}_id" => $winnerId];
                if ($winnerPairId) {
                    $update["pair{$slot}_id"] = $winnerPairId;
                }
                $nextMatch->update($update);
            }
        }

        // If this is a semifinal, propagate loser (and their pair) to the bronze match
        if ($match->round === 'semifinal' && $match->bronze_match_id) {
            $bronzeMatch = TournamentMatch::find($match->bronze_match_id);
            $loserId = $match->player1_id === $winnerId ? $match->player2_id : $match->player1_id;
            $loserPairId = $match->player1_id === $winnerId ? $match->pair2_id : $match->pair1_id;

            if ($bronzeMatch) {
                $slot = $bronzeMatch->player1_id !== null ? 2 : 1;

                $update = ["player{$slot}_id" => $loserId];
                if ($loserPairId) {
                    $update["pair{$slot}_id"] = $loserPairId;
                }
                $bronzeMatch->update($update);
            }
        }

        // Assign referee AFTER next-match players are set, so the query can
        // exclude candidates who are playing in that now-complete match slot.
        $this->assignBracketReferee($match);

        return true;
    }

    /**
     * Configure knockout phase
     *
     * @throws Exception
     */
    public function configureKnockoutPhase(Tournament $tournament, string $startingRound): bool
    {
        if ($this->checkIfAllPoolsContainsMatches($tournament) === false) {
            throw new Exception(__('Pool found without match.'));
        }

        if ($this->checkIfAllPoolsAreFinished($tournament) === false) {
            throw new Exception(__('At least one pool is still open. Please encode all matches first'));
        }

        // Delete existing knockout matches if any
        TournamentMatch::where('tournament_id', $tournament->id)->fromBracket()->delete();

        // Get qualified players based on pool standings
        $qualifiedPlayers = $this->getQualifiedPlayers($tournament, $startingRound);

        // Create bracket structure
        $result = $this->createBracket($tournament, $qualifiedPlayers, $startingRound);

        // Assign referees to the first-round bracket matches using pool non-qualifiers
        $this->assignInitialBracketReferees($tournament);

        return $result;
    }

    /**
     * Create the knockout bracket
     */
    public function createBracket(Tournament $tournament, Collection $qualifiedPlayers, string $startingRound): bool
    {
        $totalPlayers = $this->getRoundPlayerCount($startingRound);

        // Seed players in the bracket (1st from pool A vs 2nd from pool B, etc.)
        $seededPlayers = $this->seedPlayers($qualifiedPlayers);

        // Create rounds starting from final and working backwards
        $finalMatch = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 'final',
            'match_order' => 1,
            'status' => 'scheduled',
        ]);

        $bronzeMatch = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 'bronze',
            'match_order' => 1,
            'status' => 'scheduled',
            'is_bronze_match' => true,
        ]);

        $semifinalMatches = $this->createRoundMatches($tournament, 'semifinal', 2, [$finalMatch->id], $bronzeMatch->id);

        if ($startingRound === 'round_4' || $totalPlayers <= 4) {
            for ($i = 0; $i < min(count($seededPlayers), 4); $i++) {
                $semifinalMatches[intdiv($i, 2)]->update($this->buildMatchAssignment($i, $seededPlayers[$i]));
            }

            return true;
        }

        $quarterMatches = $this->createRoundMatches($tournament, 'quarterfinal', 4, $semifinalMatches->pluck('id')->toArray());

        if ($startingRound === 'round_8' || $totalPlayers <= 8) {
            for ($i = 0; $i < min(count($seededPlayers), 8); $i++) {
                $quarterMatches[intdiv($i, 2)]->update($this->buildMatchAssignment($i, $seededPlayers[$i]));
            }

            return true;
        }

        if ($startingRound === 'round_16') {
            $round16Matches = $this->createRoundMatches($tournament, 'round_16', 8, $quarterMatches->pluck('id')->toArray());

            for ($i = 0; $i < min(count($seededPlayers), 16); $i++) {
                $round16Matches[intdiv($i, 2)]->update($this->buildMatchAssignment($i, $seededPlayers[$i]));
            }
        }

        return true;
    }

    /**
     * Get all knockout matches organized by rounds
     */
    public function getKnockoutMatches(Tournament $tournament): array
    {
        // Get all matches
        $matches = TournamentMatch::where('tournament_id', $tournament->id)
            ->fromBracket()
            ->with(['player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2', 'sets', 'winner', 'referee'])
            ->orderBy('match_order')
            ->get();

        // Define the order of rounds
        $roundOrder = [
            'round_16' => 1,
            'quarterfinal' => 2,
            'semifinal' => 3,
            'final' => 4,
            'bronze' => 5,
        ];

        // Sort the collection using the defined order
        $sortedMatches = $matches->sort(function (TournamentMatch $a, TournamentMatch $b) use ($roundOrder) {
            // First sort by round
            if ($roundOrder[$a->round] !== $roundOrder[$b->round]) {
                return $roundOrder[$a->round] <=> $roundOrder[$b->round];
            }

            // If rounds are the same, sort by match_order
            return $a->match_order <=> $b->match_order;
        });

        // Group the sorted matches by round
        $rounds = [
            'round_16' => $sortedMatches->where('round', 'round_16')->values(),
            'quarterfinal' => $sortedMatches->where('round', 'quarterfinal')->values(),
            'semifinal' => $sortedMatches->where('round', 'semifinal')->values(),
            'final' => $sortedMatches->where('round', 'final')->values(),
            'bronze' => $sortedMatches->where('round', 'bronze')->values(),
        ];

        return $rounds;
    }

    /**
     * Get qualified players from pools
     */
    public function getQualifiedPlayers(Tournament $tournament, string $startingRound): Collection
    {
        $totalPlayers = $this->getRoundPlayerCount($startingRound);
        $pools = $tournament->pools;
        $qualifiedPlayers = collect();
        $playersPerPool = intdiv($totalPlayers, $pools->count());
        $remainingSpots = $totalPlayers % $pools->count();

        // Get top players from each pool
        foreach ($pools as $pool) {
            $standings = $this->tournamentMatchService->calculatePoolStandings($pool);
            for ($i = 0; $i < $playersPerPool; $i++) {
                if (isset($standings[$i])) {
                    $qualifiedPlayers->push($this->buildQualifiedEntry($standings[$i], $pool, $i + 1));
                }
            }
        }

        // Handle remaining spots with repêchage (best non-qualified players)
        if ($remainingSpots > 0) {
            $repechageCandidates = collect();

            foreach ($pools as $pool) {
                $standings = $this->tournamentMatchService->calculatePoolStandings($pool);
                for ($i = $playersPerPool; $i < count($standings); $i++) {
                    $repechageCandidates->push($this->buildQualifiedEntry($standings[$i], $pool, $i + 1));
                }
            }

            // Sort by matches won, sets won, and points
            $sortedCandidates = $repechageCandidates->sortByDesc(function (array $item) {
                return sprintf('%06d%06d%06d',
                    $item['stats']['matches_won'],
                    $item['stats']['sets_won'],
                    $item['stats']['total_points']
                );
            })->values();

            // Add best remaining players to fill spots
            for ($i = 0; $i < $remainingSpots && $i < count($sortedCandidates); $i++) {
                $qualifiedPlayers->push($sortedCandidates[$i]);
            }
        }

        return $qualifiedPlayers;
    }

    /**
     * Create matches for a specific round
     */
    protected function createRoundMatches(Tournament $tournament, string $round, int $matchCount, ?array $nextMatchIds, ?int $bronzeMatchId = null): Collection
    {
        $matches = collect();

        for ($i = 0; $i < $matchCount; $i++) {
            $nextMatchId = null;
            if (is_array($nextMatchIds)) {
                $nextMatchIndex = intdiv($i, 2);
                if (isset($nextMatchIds[$nextMatchIndex])) {
                    $nextMatchId = $nextMatchIds[$nextMatchIndex];
                }
            } else {
                $nextMatchId = $nextMatchIds;
            }

            $matchData = [
                'tournament_id' => $tournament->id,
                'round' => $round,
                'match_order' => $i + 1,
                'status' => 'scheduled',
                'next_match_id' => $nextMatchId,
            ];

            // Pour les demi-finales, ajouter la référence au match bronze directement
            if ($round === 'semifinal' && $bronzeMatchId) {
                $matchData['bronze_match_id'] = $bronzeMatchId;
            }

            $match = TournamentMatch::create($matchData);
            $matches->push($match);
        }

        return $matches;
    }

    /**
     * Get number of players needed for a specific round
     */
    protected function getRoundPlayerCount(string $round): int
    {
        switch ($round) {
            case 'round_16':
            default:
                return 16;
            case 'round_8':
                return 8;
            case 'round_4':
                return 4;
        }
    }

    /**
     * Seed players so that rank-N players face rank-(N+1) players from a different pool.
     * Players from the same pool are placed in opposite bracket halves.
     *
     * For 4 pools with 2 qualifiers each the result is:
     *   [A#1, C#2, B#1, D#2, C#1, A#2, D#1, B#2]
     * giving QF pairs: A#1-C#2, B#1-D#2, C#1-A#2, D#1-B#2.
     */
    protected function seedPlayers(Collection $qualifiedPlayers): Collection
    {
        $seededPlayers = collect();
        $poolGroups = $qualifiedPlayers->groupBy('pool.id');

        if ($poolGroups->count() < 2) {
            return $qualifiedPlayers;
        }

        $orderedPoolIds = $poolGroups->keys()->sort()->values();
        $maxPosition = $qualifiedPlayers->max('position');

        // Build one ordered group per rank (pool order is consistent within each rank)
        $rankGroups = collect();
        for ($rank = 1; $rank <= $maxPosition; $rank++) {
            $group = collect();
            foreach ($orderedPoolIds as $poolId) {
                foreach ($poolGroups[$poolId] as $player) {
                    if ($player['position'] === $rank) {
                        $group->push($player);
                        break;
                    }
                }
            }
            if ($group->isNotEmpty()) {
                $rankGroups->push($group);
            }
        }

        // Cross-seed consecutive rank pairs: rank1 vs rank2, rank3 vs rank4, …
        // Shift the lower-rank group by half its size so rank1[i] faces rank2[i + N/2].
        for ($pairIdx = 0; $pairIdx + 1 < $rankGroups->count(); $pairIdx += 2) {
            $higher = $rankGroups[$pairIdx];
            $lower = $rankGroups[$pairIdx + 1];
            $count = max($higher->count(), $lower->count());
            $offset = intdiv($count, 2);

            for ($i = 0; $i < $count; $i++) {
                if ($i < $higher->count()) {
                    $seededPlayers->push($higher[$i]);
                }
                $lowerIdx = ($i + $offset) % $count;
                if ($lowerIdx < $lower->count()) {
                    $seededPlayers->push($lower[$lowerIdx]);
                }
            }
        }

        // If odd number of rank groups, append the last unpaired group as-is
        if ($rankGroups->count() % 2 === 1) {
            foreach ($rankGroups->last() as $player) {
                $seededPlayers->push($player);
            }
        }

        // Append any repêchage or overflow players not yet placed
        foreach ($qualifiedPlayers as $player) {
            if (! $seededPlayers->contains(fn (array $v) => $v['player']->id === $player['player']->id)) {
                $seededPlayers->push($player);
            }
        }

        return $seededPlayers;
    }

    /** @param  array<string, mixed>  $seededPlayer */
    private function buildMatchAssignment(int $i, array $seededPlayer): array
    {
        $playerField = $i % 2 === 0 ? 'player1_id' : 'player2_id';
        $data = [$playerField => $seededPlayer['player']->id];

        if (isset($seededPlayer['pair'])) {
            $pairField = $i % 2 === 0 ? 'pair1_id' : 'pair2_id';
            $data[$pairField] = $seededPlayer['pair']->id;
        }

        return $data;
    }

    /** @param  array<string, mixed>  $standing */
    private function buildQualifiedEntry(array $standing, mixed $pool, int $position): array
    {
        $entry = [
            'player' => $standing['player'],
            'pool' => $pool,
            'position' => $position,
            'stats' => $standing,
        ];

        if (isset($standing['pair'])) {
            $entry['pair'] = $standing['pair'];
        }

        return $entry;
    }

    private function tryAssignBracketReferee(int $tournamentId, TournamentMatch $target): void
    {
        // All players who have lost a bracket match in this tournament
        $loserIds = TournamentMatch::where('tournament_id', $tournamentId)
            ->whereNotNull('round')
            ->whereNotNull('winner_id')
            ->get()
            ->map(fn (TournamentMatch $m) => $m->player1_id === $m->winner_id ? $m->player2_id : $m->player1_id)
            ->filter()
            ->unique()
            ->values();

        if ($loserIds->isEmpty()) {
            return;
        }

        $playingIds = collect([$target->player1_id, $target->player2_id])->filter();
        $candidates = $loserIds->diff($playingIds)->values();

        if ($candidates->isEmpty()) {
            return;
        }

        $refereeCounts = TournamentMatch::where('tournament_id', $tournamentId)
            ->whereIn('referee_id', $candidates)
            ->selectRaw('referee_id, COUNT(*) as total')
            ->groupBy('referee_id')
            ->pluck('total', 'referee_id')
            ->toArray();

        $ranked = $candidates
            ->map(fn (int $id) => ['id' => $id, 'count' => $refereeCounts[$id] ?? 0])
            ->sortBy('count');

        $minCount = $ranked->first()['count'];
        $pool = $ranked->filter(fn (array $c) => $c['count'] === $minCount)->values()->all();
        $target->update(['referee_id' => $pool[random_int(0, count($pool) - 1)]['id']]);
    }
}
