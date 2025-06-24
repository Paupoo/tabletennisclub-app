<?php

namespace App\Filament\Resources\Seasons\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SeasonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('start_year')
                    ->required()
                    ->numeric(),
                TextInput::make('end_year')
                    ->required()
                    ->numeric(),
            ]);
    }
}
