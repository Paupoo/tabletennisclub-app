<?php

namespace App\Filament\Resources\Pools\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PoolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('tournament_id')
                    ->relationship('tournament', 'name')
                    ->required(),
            ]);
    }
}
