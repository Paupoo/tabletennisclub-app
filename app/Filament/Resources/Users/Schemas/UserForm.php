<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('is_admin')
                    ->required(),
                Toggle::make('is_committee_member')
                    ->required(),
                Toggle::make('is_competitor')
                    ->required(),
                Toggle::make('has_paid')
                    ->required(),
                TextInput::make('email')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('last_name')
                    ->required(),
                Select::make('sex')
                    ->options(['MEN' => 'M e n', 'OTHER' => 'O t h e r', 'WOMEN' => 'W o m e n'])
                    ->default('OTHER')
                    ->required(),
                TextInput::make('phone_number')
                    ->tel()
                    ->default(null),
                DatePicker::make('birthdate'),
                TextInput::make('street')
                    ->default(null),
                TextInput::make('city_code')
                    ->default(null),
                TextInput::make('city_name')
                    ->default(null),
                Select::make('ranking')
                    ->options([
            'B0' => 'B0',
            'B2' => 'B2',
            'B4' => 'B4',
            'B6' => 'B6',
            'C0' => 'C0',
            'C2' => 'C2',
            'C4' => 'C4',
            'C6' => 'C6',
            'D0' => 'D0',
            'D2' => 'D2',
            'D4' => 'D4',
            'D6' => 'D6',
            'E0' => 'E0',
            'E2' => 'E2',
            'E4' => 'E4',
            'E6' => 'E6',
            'NA' => 'N a',
            'NC' => 'N c',
        ])
                    ->default('NA')
                    ->required(),
                TextInput::make('licence')
                    ->default(null),
                TextInput::make('force_list')
                    ->numeric()
                    ->default(null),
                Select::make('club_id')
                    ->relationship('club', 'name')
                    ->required()
                    ->default(1),
            ]);
    }
}
