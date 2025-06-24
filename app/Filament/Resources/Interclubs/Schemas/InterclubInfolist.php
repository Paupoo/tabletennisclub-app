<?php

namespace App\Filament\Resources\Interclubs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InterclubInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('address'),
                TextEntry::make('start_date_time')
                    ->dateTime(),
                TextEntry::make('week_number')
                    ->numeric(),
                TextEntry::make('total_players')
                    ->numeric(),
                TextEntry::make('score'),
                TextEntry::make('result'),
                TextEntry::make('visitedTeam.name')
                    ->numeric(),
                TextEntry::make('visitingTeam.name')
                    ->numeric(),
                TextEntry::make('room.name')
                    ->numeric(),
                TextEntry::make('league.id')
                    ->numeric(),
                TextEntry::make('season.name')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
