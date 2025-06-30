<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section; // Utilisez Section de Infolists
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon; // Assurez-vous que Heroicon est importé

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Utilisation d'une grille principale pour structurer les sections comme dans votre formulaire
                Grid::make(['default' => 1, 'md' => 2])
                    ->schema([
                        // Première colonne
                        Grid::make(1) // Une grille imbriquée pour empiler les sections dans la première colonne
                            ->columnSpan(1)
                            ->schema([
                                // Section Identité
                                Section::make('Identité')
                                    ->description('Informations personnelles de l\'utilisateur.')
                                    ->schema([
                                        Grid::make(2) // Sous-grille pour nom et prénom
                                            ->schema([
                                                TextEntry::make('first_name')
                                                    ->label('Prénom')
                                                    ->placeholder('Non renseigné'),
                                                TextEntry::make('last_name')
                                                    ->label('Nom')
                                                    ->placeholder('Non renseigné'),
                                            ]),
                                        TextEntry::make('sex')
                                            ->label('Sexe')
                                            ->badge()
                                            ->placeholder('Non renseigné'),
                                        TextEntry::make('birthdate')
                                            ->label('Date de Naissance')
                                            ->date('d/m/Y')
                                            ->icon(Heroicon::Calendar)
                                            ->placeholder('Non renseigné'),
                                    ]),

                                // Section Coordonnées
                                Section::make('Coordonnées')
                                    ->description('Informations de contact et d\'adresse.')
                                    ->schema([
                                        TextEntry::make('email')
                                            ->label('Adresse Email')
                                            ->copyable()
                                            ->icon(Heroicon::Envelope)
                                            ->placeholder('Non renseigné'),
                                        TextEntry::make('phone_number')
                                            ->label('Numéro de Téléphone')
                                            ->icon(Heroicon::Phone)
                                            ->placeholder('Non renseigné'),
                                        TextEntry::make('street')
                                            ->label('Rue')
                                            ->placeholder('Non renseignée'),
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('city_code')
                                                    ->label('Code Postal')
                                                    ->placeholder('Non renseigné'),
                                                TextEntry::make('city_name')
                                                    ->label('Ville')
                                                    ->placeholder('Non renseignée'),
                                            ]),
                                    ]),

                                // Section Personne responsable
                                Section::make('Personnes responsables')
                                    ->description('Informations détaillées sur la ou les personnes responsables.')
                                    ->schema([
                                        RepeatableEntry::make('guardians')
                                            ->label('')
                                            ->schema([
                                                // Maintenant, 'full_name' est un attribut directement disponible via l'accesseur
                                                TextEntry::make('full_name')
                                                    ->label('Nom Complet')
                                                    ->weight(FontWeight::Bold)
                                                    ->icon(Heroicon::User)
                                                    ->copyable(),
                                                Grid::make(2)
                                                    ->schema([
                                                        TextEntry::make('email')
                                                            ->label('Email')
                                                            ->icon(Heroicon::Envelope)
                                                            ->copyable()
                                                            ->placeholder('Non renseigné'),
                                                        TextEntry::make('phone_number')
                                                            ->label('Téléphone')
                                                            ->icon(Heroicon::Phone)
                                                            ->copyable()
                                                            ->placeholder('Non renseigné'),
                                                    ]),
                                            ])
                                            // ->hidden(fn ($record) => $record->guardians->isEmpty()),
                                            // TextEntry::make('no_guardians_message')
                                            //     ->label('Aucune personne responsable assignée.')
                                            //     ->color('gray') // Une couleur pour le distinguer
                                            //     // ->italic // En italique pour l'esthétique
                                            //     ->hidden(fn ($record) => !$record->guardians->isEmpty()),
                                    ])->hidden(fn ($record) => $record->guardians->isEmpty()),
                            ]),

                        // Deuxième colonne
                        Grid::make(1) // Une grille imbriquée pour empiler les sections dans la deuxième colonne
                            ->columnSpan(1)
                            ->schema([
                                // Section Photo de Profil
                                Section::make('Photo de Profil')
                                    ->schema([
                                        ImageEntry::make('avatar_url')
                                            ->label('') // Pas besoin de label car le titre de la section est explicite
                                            ->circular()
                                            ->size(120)
                                            ->alignment(Alignment::Center)
                                            ->defaultImageUrl(fn ($record): string =>
                                                $record->avatar_url
                                                    ? asset('storage/' . $record->avatar_url)
                                                    : 'https://ui-avatars.com/api/?name=' . urlencode($record->getFilamentName()) . '&color=FFFFFF&background=000000'
                                            ),
                                    ]),

                                // Section Paramètres
                                Section::make('Paramètres')
                                    ->description('Statuts et rôles de l\'utilisateur.')
                                    ->columns(2) // Aligne les toggles en deux colonnes
                                    ->schema([
                                        IconEntry::make('is_active')
                                            ->label('Compte Actif')
                                            ->boolean()
                                            ->trueIcon(Heroicon::CheckBadge)
                                            ->falseIcon(Heroicon::XCircle)
                                            ->trueColor('success')
                                            ->falseColor('danger')
                                            ->tooltip(fn ($state): string => $state ? 'Compte actif' : 'Compte inactif'),
                                        IconEntry::make('is_admin')
                                            ->label('Administrateur')
                                            ->boolean()
                                            ->trueIcon(Heroicon::ShieldCheck)
                                            ->falseIcon(Heroicon::ShieldExclamation)
                                            ->trueColor('primary')
                                            ->falseColor('gray')
                                            ->tooltip(fn ($state): string => $state ? 'Est administrateur' : 'N\'est pas administrateur'),
                                        IconEntry::make('is_committee_member')
                                            ->label('Membre du Comité')
                                            ->boolean()
                                            ->trueIcon(Heroicon::UserGroup)
                                            ->falseIcon(Heroicon::User)
                                            ->trueColor('primary')
                                            ->falseColor('gray')
                                            ->tooltip(fn ($state): string => $state ? 'Est membre du comité' : 'N\'est pas membre du comité'),
                                        IconEntry::make('has_paid')
                                            ->label('Cotisation Payée')
                                            ->boolean()
                                            ->trueIcon(Heroicon::CurrencyEuro)
                                            ->falseIcon(Heroicon::CreditCard)
                                            ->trueColor('success')
                                            ->falseColor('danger')
                                            ->tooltip(fn ($state): string => $state ? 'Cotisation payée' : 'Cotisation non payée'),
                                        IconEntry::make('is_competitor')
                                            ->label('Compétiteur')
                                            ->boolean()
                                            ->trueIcon(Heroicon::Trophy)
                                            ->falseIcon(Heroicon::UserMinus)
                                            ->trueColor('warning')
                                            ->falseColor('gray')
                                            ->tooltip(fn ($state): string => $state ? 'Est un compétiteur' : 'N\'est pas un compétiteur'),
                                        TextEntry::make('ranking')
                                            ->label('Classement')
                                            ->placeholder('Non renseigné')
                                            ->hidden(fn ($record) => !$record->is_competitor),
                                        TextEntry::make('licence')
                                            ->label('Numéro de Licence')
                                            ->placeholder('Non renseigné')
                                            ->hidden(fn ($record) => !$record->is_competitor),
                                        TextEntry::make('force_list')
                                            ->label('Force Liste (Numérique)')
                                            ->numeric()
                                            ->placeholder('Non renseignée'),
                                        TextEntry::make('club.name')
                                            ->label('Club')
                                            ->placeholder('Non renseigné'),
                                    ]),

                                // Section Compte
                                Section::make('Compte')
                                    ->description('Informations de connexion de l\'utilisateur.')
                                    ->schema([
                                        TextEntry::make('email_verified_at')
                                            ->label('Email Vérifié le')
                                            ->dateTime('d/m/Y H:i')
                                            ->icon(Heroicon::CheckBadge)
                                            ->placeholder('Non vérifié'),
                                        TextEntry::make('created_at')
                                            ->label('Créé le')
                                            ->dateTime('d/m/Y H:i')
                                            ->placeholder('Non renseigné'),
                                        TextEntry::make('updated_at')
                                            ->label('Dernière mise à jour le')
                                            ->dateTime('d/m/Y H:i')
                                            ->placeholder('Non renseigné'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(   ),
            ]);
    }
}