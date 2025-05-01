<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;
use App\Models\Pool;
use App\Models\Room;
use Exception;
use Illuminate\Support\Collection;

class TournamentTableService
{
    /**
     * Lie les tables de chaque salle déjà liés à un tournoi
     *
     * @param Tournament $tournament Le tournoi concerné
     * @return array Les pools créées avec leurs joueurs
     */
    public function linkAvailableTables(Tournament $tournament)
    {
        $tournament->tables()->sync([]);
        
        foreach($tournament->rooms as $room){
            foreach($room->tables as $table){
                if($table->state !== 'oos'){
                    $tournament->tables()->attach($table);
                }
            }
        }
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