<?php

namespace Database\Seeders;

use App\Models\TournamentMatch;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class TournamentMatchSeeder extends Seeder
{

    // Valid sets should look like this :
    // 'sets' => [
    //     ['player1_score' => '11', 'player2_score' => '8'],
    //     ['player1_score' => '8', 'player2_score' => '11'],
    //     ['player1_score' => '11', 'player2_score' => '7'],
    //     ['player1_score' => '11', 'player2_score' => '0'],
    //     ['player1_score' => '15', 'player2_score' => '17'],
    // ],

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TournamentMatch::all()->each(function ($match) {
            $sets = $this->playMatch(3);

            $match->recordResult($sets);
            $match->update([
                'status' => 'completed',
            ]);

        });
    }

    /**
     * Get random points
     *
     * @return integer
     */
    private function diceRoll(): int
    {
        return fake()->numberBetween(0, 15);
    }

    /**
     * Generate a set result
     *
     * @return array
     */
    private function generateValidSet(): array
    {
        $winnerScore = $this->diceRoll();

        // S'assurer que le vainqueur a au moins 11 et deux points d'écart
        $winnerScore = max($winnerScore, 11);

        if ($winnerScore > 11) {
            $loserScore = $winnerScore - 2;
        } else {
            $loserScore = fake()->numberBetween(0, 9);
        }

        // Tirer au sort le gagnant
        if (rand(0, 1)) {
            return ['player1_score' => $winnerScore, 'player2_score' => $loserScore];
        } else {
            return ['player1_score' => $loserScore, 'player2_score' => $winnerScore];
        }

    }

    /**
     * Determine who wins the set
     * @param array $set
     * @return string
     */
    private function getSetWinner(array $set): string {

        return $set['player1_score'] > $set['player2_score']
            ? "player1"
            : "player2";
    }

    /**
     * Simulate a full match, with sets until there is a winner
     * @param int $setsToWin
     * @return array[]
     */
    private function playMatch(int $setsToWin = 3): array {
        $sets = [];
        $player1WonSetsCount = 0;
        $player2WonSetsCount = 0;

        while($player1WonSetsCount < $setsToWin && $player2WonSetsCount < $setsToWin) {
            $set = $this->generateValidSet();
            
            $this->getSetWinner($set) === "player1"
                ? $player1WonSetsCount++
                : $player2WonSetsCount++;

            $sets[] = $set;
        }

        return $sets;
    }
}