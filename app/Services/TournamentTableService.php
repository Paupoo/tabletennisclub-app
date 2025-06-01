<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Room;
use App\Models\Tournament;
use App\Models\TournamentMatch;

class TournamentTableService
{
    /**
     * Lie les tables de chaque salle déjà liés à un tournoi
     *
     * @param  Tournament  $tournament  Le tournoi concerné
     * @return array Les pools créées avec leurs joueurs
     */
    public function linkAvailableTables(Tournament $tournament): void
    {
        $tablesToSync = []; // Collect tables to keep or add to the tournament

        foreach ($tournament->rooms as $room) {
            foreach ($room->tables as $table) {
                if ($table->state !== 'oos') {
                    $tablesToSync[] = $table->id;
                }
            }
        }

        $tournament->tables()->sync($tablesToSync);
    }

    public function freeUsedTable(TournamentMatch $match): void
    {
        $tournament = $match->tournament()->first();
        $table = $tournament->tables()->wherePivot('tournament_match_id', $match->id)->first();
        $tournament->tables()->updateExistingPivot($table->id, [
            'is_table_free' => true,
            'match_ended_at' => now(),
        ]);
    }

    public function updateTablesCount(Room $room): void
    {
        $total_tables = $room->tables()->count();
        $total_playable_tables = $room->tables()->where('state', '!=', 'oos')->count();
        $room->total_tables = $total_tables;
        $room->total_playable_tables = $total_playable_tables;
        $room->save();
    }
}
