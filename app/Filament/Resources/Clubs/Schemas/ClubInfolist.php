<?php

namespace App\Filament\Resources\Clubs\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ClubInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('licence'),
                TextEntry::make('street'),
                TextEntry::make('city_code'),
                TextEntry::make('city_name'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
