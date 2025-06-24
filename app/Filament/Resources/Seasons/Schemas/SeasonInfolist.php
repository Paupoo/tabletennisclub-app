<?php

namespace App\Filament\Resources\Seasons\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SeasonInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('start_year')
                    ->numeric(),
                TextEntry::make('end_year')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
