<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pool;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Support\Collection;

class TournamentMatchService
{
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
        ],
    ];

    /**
     * Generate all matches for a pool using Round Robin algorithm
     * 
     * @param Pool $pool The pool to generate matches for
     * @return Collection The generated matches
     */
    public function generateMatches(Pool $pool): Collection
    {
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
                if ($match == 0) {
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
                    'player1_handicap_points' => $pool->tournament->has_handicap_points ? $this->calculateHandicapPoints(User::find($players[$home]['id']), User::find($players[$away]['id'])) : 0,
                    'player2_handicap_points' => $pool->tournament->has_handicap_points ? $this->calculateHandicapPoints(User::find($players[$away]['id']), User::find($players[$home]['id'])) : 0,
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
     * @param Tournament $tournament The tournament
     * @return array Array of pools with their matches
     */
    public function generateTournamentMatches(Tournament $tournament): array
    {
        $result = [];
        
        foreach ($tournament->pools as $pool) {
            $result[$pool->id] = $this->generateMatches($pool);
        }
        
        return $result;
    }
    
    /**
     * Calculate standings for a pool
     * 
     * @param Pool $pool The pool to calculate standings for
     * @return Collection Collection of players with their stats
     */
    public function calculatePoolStandings(Pool $pool): Collection
    {
        $players = $pool->users;
        $matches = TournamentMatch::where('pool_id', $pool->id)->get();
        
        $standings = $players->map(function ($player) use ($matches) {
            $playerMatches = $matches->filter(function ($match) use ($player) {
                return $match->player1_id == $player->id || $match->player2_id == $player->id;
            });
            
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
        return $standings->sortByDesc(function ($item) {
            return sprintf('%06d%06d%06d', $item['matches_won'], $item['sets_won'], $item['total_points']);
        })->values();
    }

    /**
     * Calculate standings for a pool
     * 
     * @param Pool $pool The pool to calculate standings for
     * @return Collection Collection of players with their stats
     */
    public function calculateRowStandings(Tournament $tournament): Collection
    {
        $players = $tournament->users;
        $matches = TournamentMatch::where('tournament_id', $tournament->id)->get();
        
        $standings = $players->map(function ($player) use ($matches) {
            $playerMatches = $matches->filter(function ($match) use ($player) {
                return $match->player1_id == $player->id || $match->player2_id == $player->id;
            });
            
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
        return $standings->sortByDesc(function ($item) {
            return sprintf('%06d%06d%06d', $item['matches_won'], $item['sets_won'], $item['total_points']);
        })->values();
    }

    private function calculateHandicapPoints(User $player1, User $player2): int
    {
        return $this->handicapPoints[$player1->ranking][$player2->ranking];
    }
}