<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->boolean(),
                IconColumn::make('is_admin')
                    ->boolean(),
                IconColumn::make('is_committee_member')
                    ->boolean(),
                IconColumn::make('is_competitor')
                    ->boolean(),
                IconColumn::make('has_paid')
                    ->boolean(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->searchable(),
                TextColumn::make('sex'),
                TextColumn::make('phone_number')
                    ->searchable(),
                TextColumn::make('birthdate')
                    ->date()
                    ->sortable(),
                TextColumn::make('street')
                    ->searchable(),
                TextColumn::make('city_code')
                    ->searchable(),
                TextColumn::make('city_name')
                    ->searchable(),
                TextColumn::make('ranking'),
                TextColumn::make('licence')
                    ->searchable(),
                TextColumn::make('force_list')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('club.name')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
