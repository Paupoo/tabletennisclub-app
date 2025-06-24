<?php

namespace App\Filament\Resources\Rooms\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('building_name')
                    ->default(null),
                TextInput::make('street')
                    ->required(),
                TextInput::make('city_code')
                    ->required(),
                TextInput::make('city_name')
                    ->required(),
                TextInput::make('floor')
                    ->default(null),
                TextInput::make('access_description')
                    ->default(null),
                TextInput::make('capacity_for_trainings')
                    ->required()
                    ->numeric(),
                TextInput::make('capacity_for_interclubs')
                    ->required()
                    ->numeric(),
                TextInput::make('total_tables')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_playable_tables')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
