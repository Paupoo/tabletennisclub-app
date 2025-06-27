<?php

namespace App\Filament\Resources\Pools\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PoolInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('tournament.name')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
