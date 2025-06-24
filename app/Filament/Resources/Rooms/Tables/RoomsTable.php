<?php

namespace App\Filament\Resources\Rooms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('building_name')
                    ->searchable(),
                TextColumn::make('street')
                    ->searchable(),
                TextColumn::make('city_code')
                    ->searchable(),
                TextColumn::make('city_name')
                    ->searchable(),
                TextColumn::make('floor')
                    ->searchable(),
                TextColumn::make('access_description')
                    ->searchable(),
                TextColumn::make('capacity_for_trainings')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('capacity_for_interclubs')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_tables')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_playable_tables')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
