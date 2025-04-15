<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pool;
use App\Models\PoolMatch;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use Illuminate\Support\Collection;

class TournamentMatchService
{
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
                    'player1_id' => $players[$home]['id'],
                    'player2_id' => $players[$away]['id'],
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
}