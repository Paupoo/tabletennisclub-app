<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Sex;
use App\Models\User;
use App\Services\ForceList;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Throwable;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('Avatar')
                    ->circular()
                    ->defaultImageUrl(fn ($record): string =>
                    $record->avatar_url
                        ? asset('storage/' . $record->avatar_url)
                        : 'https://ui-avatars.com/api/?name=' . urlencode($record->getFilamentName()) . '&color=FFFFFF&background=000000'
                    ),
                TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('teams.name')
                    ->label('Teams')
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggledHiddenByDefault(true),
                IconColumn::make('is_active')
                    ->boolean()
                    ->hidden(),
                IconColumn::make('is_admin')
                    ->boolean()
                    ->toggledHiddenByDefault(true)
                    ->hidden(),
                IconColumn::make('is_committee_member')
                    ->boolean()
                    ->toggledHiddenByDefault(true)
                    ->hidden(),
                IconColumn::make('is_competitor')
                    ->boolean()
                    ->toggledHiddenByDefault(true)
                    ->hidden(),
                IconColumn::make('has_paid')
                    ->boolean()
                    ->toggledHiddenByDefault(true)
                    ->hidden(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggledHiddenByDefault(true)
                    ->hidden(),
                IconColumn::make('sex')
                    ->icon(fn (string $state) => match($state) {
                        Sex::MEN->name => Heroicon::ArrowUpRight,
                        Sex::WOMEN->name => Heroicon::ArrowDownCircle,
                        default => Heroicon::QuestionMarkCircle,
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone_number')
                    ->searchable()
                    ->toggledHiddenByDefault(true)
                    ->hidden(),
                TextColumn::make('birthdate')
                    ->date()
                    ->sortable()
                    ->toggledHiddenByDefault(true)
                    ->hidden(),
                TextColumn::make('street')
                    ->searchable()
                    ->toggledHiddenByDefault(true)
                    ->hidden(),
                TextColumn::make('city_code')
                    ->searchable()
                    ->toggledHiddenByDefault(true)
                    ->hidden(),
                TextColumn::make('city_name')
                    ->searchable()
                    ->toggledHiddenByDefault(true)
                    ->hidden(),
                TextColumn::make('force_list')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ranking')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('licence')
                    ->searchable(),
                TextColumn::make('club.name')
                    ->hidden(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('guardians.first_name')
                    ->label(__('Responsible Persons')),
            ])
            ->filters([
                TernaryFilter::make('is_competitor'),
                TernaryFilter::make('is_committee_member')
                    ->label('Committee member')
                    ->nullable()
                    ->placeholder('All users')
                    ->trueLabel('Committee members')
                    ->falseLabel('Members'),
                TernaryFilter::make('is_active')
                    ->default(true),
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
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('has_paid')
                        ->label('Mark as paid')
                        ->icon(Heroicon::CheckCircle)
                        ->action(fn(Collection $records) => $records->each(fn($record) => $record->update([
                            'has_paid' => true,
                        ])))
                        ->successNotification(
                            Notification::make()
                                ->success()
                                ->title('Payment status updated')
                                ->body('Subscription fees have been marked as paid.')
                        ),
                    BulkAction::make('has_not_paid')
                        ->label('Mark as not paid')
                        ->icon(Heroicon::XMark)
                        ->action(fn(Collection $records) => $records->each(fn($record) => $record->update([
                            'has_paid' => false,
                        ])))
                        ->successNotification(
                            Notification::make()
                                ->warning()
                                ->title('Payment status updated')
                                ->body('Subscription fees have been marked as paid.')
                        ),
                    DeleteBulkAction::make(),
                ]),
                Action::make('update_force_list')
                    ->label('Update force list')
                    ->color('secondary')
                    ->action(function () {
                        try {
                            $service = new ForceList();

                            $service->setOrUpdateAll();
                            Notification::make()
                                ->title('Force list updated successfully')
                                ->success()
                                ->send();

                        } catch (Throwable $th) {
                            Notification::make()
                                ->title('Error updating force list')
                                ->body($th->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation(),
                Action::make('delete-force-list')
                    ->label('Delete force list')
                    ->color('danger')
                    ->icon(Heroicon::Backspace)
                    ->action(function () {
                        try {
                            $service = new ForceList();

                            $service->delete();

                            Notification::make()
                                ->warning()
                                ->title('Force list has been deleted')
                                ->send();

                        } catch (Throwable $th) {
                            Notification::make()
                                ->error()
                                ->title('Force list could not be deleted')
                                ->body($th->getMessage())
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
            ]);
    }
}
