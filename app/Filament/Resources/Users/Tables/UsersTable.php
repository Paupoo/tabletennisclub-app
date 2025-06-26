<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('force_list')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggledHiddenByDefault(true),
                IconColumn::make('is_active')
                    ->boolean()
                    ->toggledHiddenByDefault(true),
                IconColumn::make('is_admin')
                    ->boolean()
                    ->toggledHiddenByDefault(true),
                IconColumn::make('is_committee_member')
                    ->boolean()
                    ->toggledHiddenByDefault(true),
                IconColumn::make('is_competitor')
                    ->boolean()
                    ->toggledHiddenByDefault(true),
                IconColumn::make('has_paid')
                    ->boolean()
                    ->toggledHiddenByDefault(true),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggledHiddenByDefault(true),
                TextColumn::make('sex')
                    ->sortable()
                    ->toggledHiddenByDefault(true),
                TextColumn::make('phone_number')
                    ->searchable()
                    ->toggledHiddenByDefault(true),
                TextColumn::make('birthdate')
                    ->date()
                    ->sortable()
                    ->toggledHiddenByDefault(true),
                TextColumn::make('street')
                    ->searchable()
                    ->toggledHiddenByDefault(true),
                TextColumn::make('city_code')
                    ->searchable()
                    ->toggledHiddenByDefault(true),
                TextColumn::make('city_name')
                    ->searchable()
                    ->toggledHiddenByDefault(true),
                TextColumn::make('ranking'),
                TextColumn::make('licence')
                    ->searchable(),
                TextColumn::make('club.name')
                    ->numeric()
                    ->sortable()
                    ->toggledHiddenByDefault(),
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
                TernaryFilter::make('is_competitor'),
                TernaryFilter::make('is_committee_member')
                    ->label('Committee member')
                    ->nullable()
                    ->placeholder('All users')
                    ->trueLabel('Committee members')
                    ->falseLabel('Members'),
                TernaryFilter::make('is_active'),
                TernaryFilter::make('is_admin'),
                TernaryFilter::make('has_paid'),                
                TernaryFilter::make('email_verified_at')
                    ->label('Email verification')
                    ->nullable()
                    ->placeholder('All users')
                    ->trueLabel('Verified users')
                    ->falseLabel('Not verified users')
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
