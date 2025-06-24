<?php

namespace App\Filament\Resources\Trainings\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class TrainingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('level')
                    ->options([
            'BEGINNERS' => 'B e g i n n e r s',
            'ELITE' => 'E l i t e',
            'INTERMEDIATE' => 'I n t e r m e d i a t e',
            'KIDS' => 'K i d s',
            'OPEN' => 'O p e n',
            'YOUNG_POTENTIAL' => 'Y o u n g  p o t e n t i a l',
        ])
                    ->required(),
                Select::make('type')
                    ->options(['DIRECTED' => 'D i r e c t e d', 'FREE' => 'F r e e', 'SUPERVISED' => 'S u p e r v i s e d'])
                    ->required(),
                DateTimePicker::make('start')
                    ->required(),
                DateTimePicker::make('end')
                    ->required(),
                Select::make('room_id')
                    ->relationship('room', 'name')
                    ->required(),
                Select::make('trainer_id')
                    ->relationship('trainer', 'id')
                    ->default(null),
                Select::make('season_id')
                    ->relationship('season', 'name')
                    ->required(),
            ]);
    }
}
