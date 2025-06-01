<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use Exception;
use Illuminate\Support\Collection;

class TournamentFinalPhaseService
{
    public function __construct(
        private TournamentMatchService $tournamentMatchService,
        private TournamentPoolService $tournamentPoolService,
    ) {}

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
            if ($pool->tournamentMatches->count() === 0) {
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

        // If there's a next match, update it with the winner
        if ($match->next_match_id) {
            $nextMatch = TournamentMatch::find($match->next_match_id);
            if ($nextMatch) {
                // Determine which player field to update
                $playerField = 'player1_id';
                if ($nextMatch->player1_id) {
                    $playerField = 'player2_id';
                }
                $nextMatch->update([
                    $playerField => $winnerId,
                ]);
            }
        }

        // If this is a semifinal, update bronze match with loser
        if ($match->round === 'semifinal' && $match->bronze_match_id) {
            $bronzeMatch = TournamentMatch::find($match->bronze_match_id);
            $loserId = $match->player1_id === $winnerId ? $match->player2_id : $match->player1_id;

            if ($bronzeMatch) {
                // Determine which player field to update
                $playerField = 'player1_id';
                // Only check if player1_id is NULL (not just any value)
                if ($bronzeMatch->player1_id !== null) {
                    $playerField = 'player2_id';
                }

                $bronzeMatch->update([
                    $playerField => $loserId,
                ]);
            }
        }

        return true;
    }

    /**
     * Configure knockout phase
     *
     * @param  string  $startingRound  ('round_16', 'round_8', 'round_4')
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
        return $this->createBracket($tournament, $qualifiedPlayers, $startingRound);
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

        $semifinalMatches = $this->createRoundMatches($tournament, 'semifinal', 2, $finalMatch->id, $bronzeMatch->id);

        if ($startingRound === 'round_4' || $totalPlayers <= 4) {
            // If starting with semifinals, assign players directly
            for ($i = 0; $i < min(count($seededPlayers), 4); $i++) {
                $matchIndex = intdiv($i, 2);
                $playerField = $i % 2 === 0 ? 'player1_id' : 'player2_id';

                $semifinalMatches[$matchIndex]->update([
                    $playerField => $seededPlayers[$i]['player']->id,
                ]);
            }

            return true;
        }

        $quarterMatches = $this->createRoundMatches($tournament, 'quarterfinal', 4, $semifinalMatches->pluck('id')->toArray());

        if ($startingRound === 'round_8' || $totalPlayers <= 8) {
            // If starting with quarterfinals, assign players directly
            for ($i = 0; $i < min(count($seededPlayers), 8); $i++) {
                $matchIndex = intdiv($i, 2);
                $playerField = $i % 2 === 0 ? 'player1_id' : 'player2_id';
                $quarterMatches[$matchIndex]->update([
                    $playerField => $seededPlayers[$i]['player']->id,
                ]);
            }

            return true; // Ceci est correct, mais le code continue malgré tout
        }

        // Il manque les accolades autour de ce bloc
        if ($startingRound === 'round_16') {
            $round16Matches = $this->createRoundMatches($tournament, 'round_16', 8, $quarterMatches->pluck('id')->toArray());

            // Assign players to first round
            for ($i = 0; $i < min(count($seededPlayers), 16); $i++) {
                $matchIndex = intdiv($i, 2);
                $playerField = $i % 2 === 0 ? 'player1_id' : 'player2_id';
                $round16Matches[$matchIndex]->update([
                    $playerField => $seededPlayers[$i]['player']->id,
                ]);
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
        $sortedMatches = $matches->sort(function ($a, $b) use ($roundOrder) {
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
                    $qualifiedPlayers->push([
                        'player' => $standings[$i]['player'],
                        'pool' => $pool,
                        'position' => $i + 1,
                        'stats' => $standings[$i],
                    ]);
                }
            }
        }

        // Handle remaining spots with repêchage (best non-qualified players)
        if ($remainingSpots > 0) {
            $repechageCandidates = collect();

            foreach ($pools as $pool) {
                $standings = $this->tournamentMatchService->calculatePoolStandings($pool);
                for ($i = $playersPerPool; $i < count($standings); $i++) {
                    $repechageCandidates->push([
                        'player' => $standings[$i]['player'],
                        'pool' => $pool,
                        'position' => $i + 1,
                        'stats' => $standings[$i],
                    ]);
                }
            }

            // Sort by matches won, sets won, and points
            $sortedCandidates = $repechageCandidates->sortByDesc(function ($item) {
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
     *
     * @param  mixed  $nextMatchIds
     */
    protected function createRoundMatches(Tournament $tournament, string $round, int $matchCount, $nextMatchIds, ?int $bronzeMatchId = null): Collection
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
                return 16;
            case 'round_8':
                return 8;
            case 'round_4':
                return 4;
            default:
                return 16;
        }
    }

    /**
     * Seed players in bracket to avoid early matchups between top players from same pool
     */
    protected function seedPlayers(Collection $qualifiedPlayers): Collection
    {
        $seededPlayers = collect();
        $poolGroups = $qualifiedPlayers->groupBy('pool.id');

        // Ensure we have enough players
        if ($poolGroups->count() < 2) {
            return $qualifiedPlayers;
        }

        // Get pools in consistent order
        $orderedPools = $poolGroups->keys()->sort()->values();

        // Pair pools opposite to each other (first vs last, second vs second-to-last, etc.)
        $poolPairs = collect();
        for ($i = 0; $i < intdiv($orderedPools->count(), 2); $i++) {
            $poolPairs->push([
                $orderedPools[$i],
                $orderedPools[$orderedPools->count() - 1 - $i],
            ]);
        }

        // For each position (1st place, 2nd place, etc.)
        $maxPosition = $qualifiedPlayers->max('position');
        for ($position = 1; $position <= $maxPosition; $position++) {
            foreach ($poolPairs as $poolPair) {
                // Add 1st from pool A, then 2nd from pool B
                foreach ($poolGroups[$poolPair[0]] as $player) {
                    if ($player['position'] === $position) {
                        $seededPlayers->push($player);
                        break;
                    }
                }

                // Add 2nd from pool B, then 1st from pool A (for next iteration)
                foreach ($poolGroups[$poolPair[1]] as $player) {
                    if ($player['position'] === $position) {
                        $seededPlayers->push($player);
                        break;
                    }
                }
            }
        }

        // Add any remaining players (from repêchage)
        foreach ($qualifiedPlayers as $player) {
            if (! $seededPlayers->contains(function ($value, $key) use ($player) {
                return $value['player']->id === $player['player']->id;
            })) {
                $seededPlayers->push($player);
            }
        }

        return $seededPlayers;
    }
}
