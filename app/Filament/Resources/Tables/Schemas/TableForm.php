<?php

namespace App\Filament\Resources\Tables\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                DatePicker::make('purchased_on'),
                TextInput::make('state')
                    ->default(null),
                Select::make('room_id')
                    ->relationship('room', 'name')
                    ->default(null),
            ]);
    }
}
