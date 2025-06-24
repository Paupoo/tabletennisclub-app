<?php

namespace App\Filament\Resources\Teams\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TeamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('league_id')
                    ->relationship('league', 'id')
                    ->default(null),
                Select::make('club_id')
                    ->relationship('club', 'name')
                    ->default(null),
                Select::make('captain_id')
                    ->relationship('captain', 'id')
                    ->default(null),
                Select::make('season_id')
                    ->relationship('season', 'name')
                    ->required(),
            ]);
    }
}
