<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Services;

use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use Exception;
use Illuminate\Support\Collection;

class TournamentPoolService
{
    public function distributePairsInPools(Tournament $tournament, int $numberOfPools): array
    {
        $pairs = TournamentPair::where('tournament_id', $tournament->id)
            ->with(['player1', 'player2'])
            ->get()
            ->sortBy(fn (TournamentPair $pair): int => $pair->seedIndex())
            ->values();

        if ($pairs->isEmpty()) {
            return [];
        }

        $pools = $this->createPools($tournament, $numberOfPools);
        $poolCount = $pools->count();
        $poolIndex = 0;

        foreach ($pairs as $pair) {
            $pools[$poolIndex]->attachPair($pair);

            $poolIndex = ($poolIndex === $poolCount - 1) ? 0 : $poolIndex + 1;
        }

        return $pools->toArray();
    }

    /**
     * Répartit les joueurs inscrits à un tournoi dans des pools
     *
     * @param  Tournament  $tournament  Le tournoi concerné
     * @param  int  $numberOfPools  Le nombre de pools à créer
     * @return array Les pools créées avec leurs joueurs
     */
    public function distributePlayersInPools(Tournament $tournament, int $numberOfPools): array
    {
        if ($tournament->match_type === 'double') {
            return $this->distributePairsInPools($tournament, $numberOfPools);
        }

        $players = $tournament->users()
            ->whereIn('tournament_user.registration_status', ['registered', 'confirmed'])
            ->orderBy('ranking')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        if ($players->isEmpty()) {
            return [];
        }

        $pools = $this->createPools($tournament, $numberOfPools);
        $this->assignPlayersToPools($players, $pools);

        return $pools->toArray();
    }

    public function hasPoolMatches(Pool $pool): bool
    {
        $total_matches = $pool->tournamentmatches()->count();

        return $total_matches > 0;
    }

    public function isPoolFinished(Pool $pool): bool
    {
        $total_matches = $pool->tournamentmatches()->count();
        $totalMatchesCompleted = $pool->tournamentmatches()->where('status', 'completed')->count();

        return $total_matches === $totalMatchesCompleted;
    }

    /**
     * Distribue les joueurs dans les pools en serpentin
     * (meilleur joueur dans pool A, 2ème dans B, etc., puis retour)
     *
     * @param  Collection  $players  Collection de joueurs triés par ranking
     * @param  Collection  $pools  Collection de pools
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
            } catch (Exception $e) {
                // Log l'erreur si besoin
                throw new Exception('Something went wrong while setting a player into ' . $currentPool->name, $e->getCode(), $e);
            }

            // Reseter l'index si on a terminé de distribuer un joueur dans chaque poule
            if ($poolIndex === $poolCount - 1) {
                $poolIndex = 0;
            } else {
                $poolIndex++;
            }

        }
    }

    /**
     * Crée les pools pour le tournoi avec des noms A, B, C, etc.
     *
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
            $pool = new Pool;
            $pool->name = "Pool {$poolName}";
            $pool->tournament_id = $tournament->id;
            $pool->save();
            $pools->push($pool);
        }

        return $pools;
    }
}
