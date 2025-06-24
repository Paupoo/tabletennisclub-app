<?php

namespace App\Filament\Resources\Teams\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TeamInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('league.id')
                    ->numeric(),
                TextEntry::make('club.name')
                    ->numeric(),
                TextEntry::make('captain.id')
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
