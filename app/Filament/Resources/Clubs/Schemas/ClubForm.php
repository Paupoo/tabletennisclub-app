<?php

namespace App\Filament\Resources\Clubs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ClubForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('licence')
                    ->required(),
                TextInput::make('street')
                    ->default(null),
                TextInput::make('city_code')
                    ->default(null),
                TextInput::make('city_name')
                    ->default(null),
            ]);
    }
}
