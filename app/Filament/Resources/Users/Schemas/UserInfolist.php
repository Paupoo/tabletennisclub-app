<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('force_list')
                    ->numeric(),
                TextEntry::make('first_name'),
                TextEntry::make('last_name'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('ranking'),
                TextEntry::make('email'),
                IconEntry::make('is_admin')
                    ->boolean(),
                IconEntry::make('is_committee_member')
                    ->boolean(),
                IconEntry::make('is_competitor')
                    ->boolean(),
                IconEntry::make('has_paid')
                    ->boolean(),
                TextEntry::make('email_verified_at')
                    ->dateTime(),
                TextEntry::make('sex'),
                TextEntry::make('phone_number'),
                TextEntry::make('birthdate')
                    ->date(),
                TextEntry::make('street'),
                TextEntry::make('city_code'),
                TextEntry::make('city_name'),
                TextEntry::make('licence'),
                TextEntry::make('club.name')
                    ->numeric(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                IconEntry::make('is_active')
                    ->boolean(),
            ]);
    }
}
