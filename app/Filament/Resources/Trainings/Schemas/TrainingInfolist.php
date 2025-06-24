<?php

namespace App\Filament\Resources\Trainings\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TrainingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('level'),
                TextEntry::make('type'),
                TextEntry::make('start')
                    ->dateTime(),
                TextEntry::make('end')
                    ->dateTime(),
                TextEntry::make('room.name')
                    ->numeric(),
                TextEntry::make('trainer.id')
                    ->numeric(),
                TextEntry::make('season.name')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
