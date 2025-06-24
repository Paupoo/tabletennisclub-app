<?php

namespace App\Filament\Resources\Tournaments\Schemas;

use App\Enums\TournamentStatusEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TournamentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                DateTimePicker::make('start_date'),
                DateTimePicker::make('end_date'),
                TextInput::make('total_users')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('max_users')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                Select::make('status')
                    ->options(TournamentStatusEnum::class)
                    ->default('draft')
                    ->required(),
                Toggle::make('has_handicap_points')
                    ->required(),
            ]);
    }
}
