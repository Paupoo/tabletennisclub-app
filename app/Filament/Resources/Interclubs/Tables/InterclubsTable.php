<?php

namespace App\Filament\Resources\Interclubs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InterclubsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('address')
                    ->searchable(),
                TextColumn::make('start_date_time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('week_number')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_players')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('score')
                    ->searchable(),
                TextColumn::make('result'),
                TextColumn::make('visitedTeam.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('visitingTeam.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('room.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('league.id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('season.name')
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
