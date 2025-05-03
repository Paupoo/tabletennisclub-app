<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;
use App\Models\Pool;
use Exception;
use Illuminate\Support\Collection;

class TournamentPoolService
{

    /**
     * Répartit les joueurs inscrits à un tournoi dans des pools
     *
     * @param Tournament $tournament Le tournoi concerné
     * @param int $numberOfPools Le nombre de pools à créer
     * @return array Les pools créées avec leurs joueurs
     */
    public function distributePlayersInPools(Tournament $tournament, int $numberOfPools): array
    {
        // 1. Récupérer tous les joueurs inscrits au tournoi, triés par ranking
        $players = $tournament->users()
            ->orderBy('ranking')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
            
        if ($players->isEmpty()) {
            return [];
        }

        // 2. Créer les pools avec les noms A, B, C, etc.
        $pools = $this->createPools($tournament, $numberOfPools);
        
        // 3. Répartir les joueurs en serpentin (le meilleur dans la pool A, le 2ème dans la B, etc.)
        $this->assignPlayersToPools($players, $pools);
        
        return $pools->toArray();
    }
    
    /**
     * Crée les pools pour le tournoi avec des noms A, B, C, etc.
     *
     * @param Tournament $tournament
     * @param int $numberOfPools
     * @return Collection Collection de pools
     */
    private function createPools(Tournament $tournament, int $numberOfPools): Collection
    {
        $pools = collect();
        
        // Supprimer les pools existantes si nécessaire
        $tournament->pools()->delete();
        
        // Créer les nouvelles pools
        for ($i = 0; $i < $numberOfPools; $i++) {
            $poolName = chr(65 + $i); // A=65, B=66, etc. en ASCII
            $pool = new Pool();
            $pool->name = "Pool $poolName";
            $pool->tournament_id = $tournament->id;
            $pool->save();
            $pools->push($pool);
        }
        
        return $pools;
    }
    
    /**
     * Distribue les joueurs dans les pools en serpentin
     * (meilleur joueur dans pool A, 2ème dans B, etc., puis retour)
     *
     * @param Collection $players Collection de joueurs triés par ranking
     * @param Collection $pools Collection de pools
     */
    private function assignPlayersToPools(Collection $players, Collection $pools): void
    {
        $poolCount = $pools->count();
        if ($poolCount === 0) {
            return;
        }
        
        $poolIndex = 0;
        
        foreach ($players as $player) {
            // Obtenir la pool actuelle
            $currentPool = $pools[$poolIndex];
            
            // Attacher le joueur à la pool
            try {
                $currentPool->attachUser($player);
            } catch (\Exception $e) {
                // Log l'erreur si besoin
                throw new Exception('Something went wrong while setting a player into ' . $currentPool->name);
                continue;
            }
            
            // Reseter l'index si on a terminé de distribuer un joueur dans chaque poule
                if($poolIndex === $poolCount - 1){
                    $poolIndex = 0;
                } else {
                    $poolIndex ++;
            }

           }
    }

    public function isPoolFinished(Pool $pool): bool
    {
        $result = true;

        foreach($pool->tournamentmatches as $match){
            if($match->status !== 'completed'){
                $result = false;
            } 
        }

        return $result;
    }
}