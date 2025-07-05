<?php

namespace App\Filament\Resources\Users\Widgets;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestUsers extends TableWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => User::query())
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('avatar_url')
                    ->searchable(),
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
                IconColumn::make('emails_notifications')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}
