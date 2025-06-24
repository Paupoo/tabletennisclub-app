<?php

namespace App\Filament\Resources\Interclubs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InterclubForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('address')
                    ->required(),
                DateTimePicker::make('start_date_time')
                    ->required(),
                TextInput::make('week_number')
                    ->numeric()
                    ->default(null),
                TextInput::make('total_players')
                    ->required()
                    ->numeric(),
                TextInput::make('score')
                    ->default(null),
                Select::make('result')
                    ->options(['Draw' => 'Draw', 'Loss' => 'Loss', 'Win' => 'Win', 'Withdrawal' => 'Withdrawal'])
                    ->default(null),
                Select::make('visited_team_id')
                    ->relationship('visitedTeam', 'name')
                    ->default(null),
                Select::make('visiting_team_id')
                    ->relationship('visitingTeam', 'name')
                    ->default(null),
                Select::make('room_id')
                    ->relationship('room', 'name')
                    ->default(null),
                Select::make('league_id')
                    ->relationship('league', 'id')
                    ->default(null),
                Select::make('season_id')
                    ->relationship('season', 'name')
                    ->default(null),
            ]);
    }
}
