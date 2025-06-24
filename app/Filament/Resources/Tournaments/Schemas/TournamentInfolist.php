<?php

namespace App\Filament\Resources\Tournaments\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TournamentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('start_date')
                    ->dateTime(),
                TextEntry::make('end_date')
                    ->dateTime(),
                TextEntry::make('total_users')
                    ->numeric(),
                TextEntry::make('max_users')
                    ->numeric(),
                TextEntry::make('price')
                    ->money(),
                TextEntry::make('status'),
                IconEntry::make('has_handicap_points')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
