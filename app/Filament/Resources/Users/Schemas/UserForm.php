<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('UserTabs')
                ->tabs([
                    Tab::make(__('Identity'))
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 2])
                                ->schema([
                                    Section::make([
                                        TextInput::make('first_name')
                                            ->string()
                                            ->maxLength(255)
                                            ->required(),
                                        TextInput::make('last_name')
                                            ->string()
                                            ->maxLength(255)
                                            ->required(),
                                        Select::make('sex')
                                            ->options([
                                                'MEN' => 'Men',
                                                'OTHER' => 'Other',
                                                'WOMEN' => 'Women',
                                            ])
                                            ->default('OTHER')
                                            ->required(),
                                        DatePicker::make('birthdate')
                                            ->before('today')
                                            ->live(onBlur: true),
                                    ])->label(__('Identity')),
                                    Section::make([
                                        TextInput::make('street')
                                            ->label( __('Street'))
                                            ->string()
                                            ->hint(__('Street and house number'))
                                            ->maxLength(255)
                                            ->default(null),
                                        TextInput::make('city_code')
                                            ->label('Code Postal')
                                            ->default(null)
                                            ->minLength(4)
                                            ->maxLength(4)
                                            ->numeric()
                                            ->hint(''),
                                        TextInput::make('city_name')
                                            ->string()
                                            ->maxLength(255)
                                            ->default(null),
                                        TextInput::make('phone_number')
                                            ->tel()
                                            ->regex('^(((\+|00)32[ ]?(?:\(0\)[ ]?)?)|0){1}(4(60|[789]\d)\/?(\s?\d{2}\.?){2}(\s?\d{2})|(\d\/?\s?\d{3}|\d{2}\/?\s?\d{2})(\.?\s?\d{2}){2})$^')
                                            ->default(null),
                                    ])->label(__('Contact Info')),
                                    Section::make([
                                        Select::make('guardian_id')
                                            ->multiple()
                                            ->relationship('guardians', 'id')
                                            ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->first_name} {$record->last_name}")
                                            ->searchable(['first_name', 'last_name'])
                                            ->preload()
                                            ->required()
                                            ->createOptionForm([
                                                TextInput::make('first_name')
                                                    ->string()
                                                    ->maxLength(255)
                                                    ->required(),
                                                TextInput::make('last_name')
                                                    ->string()
                                                    ->maxLength(255)
                                                    ->required(),
                                                TextInput::make('phone_number')
                                                    ->tel()
                                                    ->regex('^(((\+|00)32[ ]?(?:\(0\)[ ]?)?)|0){1}(4(60|[789]\d)\/?(\s?\d{2}\.?){2}(\s?\d{2})|(\d\/?\s?\d{3}|\d{2}\/?\s?\d{2})(\.?\s?\d{2}){2})$^')
                                                    ->default(null),
                                                TextInput::make('email')
                                                    ->email()
                                                    ->unique()
                                                    ->required(),
                                                TextInput::make('password')
                                                    ->hint(__('Min 8 characters, including special characters, numbers and mixed case'))
                                                    ->password()
                                                    ->confirmed()
                                                    ->rules(['confirmed', 'min:8', Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()])
                                                    ->revealable()
                                                    ->required(),
                                                TextInput::make('password_confirmation')
                                                    ->password()
                                                    ->revealable()
                                                    ->required(),
                                            ]),
                                    ])->label('Responsible person')
                                        ->hidden(fn (Get $get): bool => $get('birthdate') === null || (Carbon::parse($get('birthdate'))->diffInYears(Carbon::now()) >= 18)),
                                ]),
                        ]),
                    Tab::make(__('Parameters'))
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 2])
                                ->schema([
                                    Section::make([
                                        Toggle::make('is_active')
                                            ->required(),
                                        Toggle::make('is_admin')
                                            ->required(),
                                        Toggle::make('is_committee_member')
                                            ->required(),
                                        Toggle::make('has_paid')
                                            ->required(),
                                    ]),
                                    Section::make([
                                        Toggle::make('is_competitor')
                                            ->required()
                                            ->live(),
                                        Select::make('ranking')
                                            ->options([
                                                'B0' => 'B0', 'B2' => 'B2', 'B4' => 'B4', 'B6' => 'B6',
                                                'C0' => 'C0', 'C2' => 'C2', 'C4' => 'C4', 'C6' => 'C6',
                                                'D0' => 'D0', 'D2' => 'D2', 'D4' => 'D4', 'D6' => 'D6',
                                                'E0' => 'E0', 'E2' => 'E2', 'E4' => 'E4', 'E6' => 'E6',
                                                'NC' => 'NC', 'NA' => 'NA',
                                            ])
                                            ->default('NA')
                                            ->notIn('NA', fn (Get $get):bool => $get('is_competitor'))
                                            ->required(),
                                        TextInput::make('licence')
                                            ->default(null)
                                            ->requiredIf('is_competitor', true)
                                            ->unique()
                                            ->length(6),
                                        TextInput::make('force_list')
                                            ->integer()
                                            ->default(null)
                                            ->disabled(),
                                        Select::make('club_id')
                                            ->relationship('club', 'name')
                                            ->default(1)
                                            ->hidden(),
                                    ]),             
                                ]),
                    ]),
                    Tab::make(__('Account'))
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 2])
                                ->schema([
                                    TextInput::make('email')
                                        ->email()
                                        ->unique()
                                        ->required(),
                                    DateTimePicker::make('email_verified_at')
                                        ->hidden(fn (string $operation): bool => $operation === 'create'),
                                    TextInput::make('password')
                                        ->password()
                                        ->confirmed()
                                        ->rules(['confirmed', 'min:8', Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()])
                                        ->revealable()
                                        ->hint(__('Min 8 characters, including special characters, numbers and mixed case'))
                                        // ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)) // Not needed because I already hash the password in the casts
                                        ->dehydrated(fn (?string $state): bool => filled($state))
                                        ->required(fn (string $operation): bool => $operation === 'create'),
                                    TextInput::make('password_confirmation')
                                        ->password()
                                        ->revealable()
                                        // ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)) // Not needed because I already hash the password in the casts
                                        ->dehydrated(fn (?string $state): bool => filled($state))
                                        ->required(fn (string $operation): bool => $operation === 'create'),
                                    FileUpload::make('avatar_url')
                                        ->label('Image de profil')
                                        ->image()
                                        ->disk('public')
                                        ->directory('avatars')
                                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                                        ->maxSize(1024)
                                        ->nullable(), 
                                ]),
                    ]),
                ])
                ->columnSpanFull(),
        ]);
    }
}
