<?php

namespace App\Filament\Resources\Leagues\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LeagueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('division')
                    ->required(),
                Select::make('level')
                    ->options([
            'NATIONAL' => 'N a t i o n a l',
            'PROVINCIAL_BW' => 'P r o v i n c i a l  b w',
            'REGIONAL' => 'R e g i o n a l',
        ])
                    ->required(),
                Select::make('category')
                    ->options(['MEN' => 'M e n', 'VETERANS' => 'V e t e r a n s', 'WOMEN' => 'W o m e n'])
                    ->required(),
                Select::make('season_id')
                    ->relationship('season', 'name')
                    ->required(),
            ]);
    }
}
