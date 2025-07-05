<?php

namespace App\Filament\Resources\Articles\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class ArticleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Grille principale pour structurer les sections
                Grid::make(['default' => 1, 'md' => 2])
                    ->schema([
                        // Première colonne
                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                // Section Contenu Principal
                                Section::make('Contenu Principal')
                                    ->description('Informations principales de l\'article.')
                                    ->schema([
                                        TextEntry::make('title')
                                            ->label('Titre')
                                            ->weight(FontWeight::Bold)
                                            ->icon(Heroicon::DocumentText)
                                            ->copyable()
                                            ->placeholder('Non renseigné'),
                                        TextEntry::make('slug')
                                            ->label('Slug')
                                            ->icon(Heroicon::Link)
                                            ->copyable()
                                            ->placeholder('Non généré'),
                                        TextEntry::make('category')
                                            ->label('Catégorie')
                                            ->badge()
                                            ->color('primary')
                                            ->icon(Heroicon::Tag)
                                            ->placeholder('Non assignée'),
                                        TextEntry::make('user.full_name')
                                            ->label('Auteur')
                                            ->icon(Heroicon::User)
                                            ->placeholder('Non renseigné'),
                                    ]),

                                // Section Statut et Publication
                                Section::make('Statut et Publication')
                                    ->description('Informations sur l\'état et la visibilité de l\'article.')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('status')
                                                    ->label('Statut')
                                                    ->badge()
                                                    ->color(fn (string $state): string => match ($state) {
                                                        'published' => 'success',
                                                        'draft' => 'warning',
                                                        'archived' => 'gray',
                                                        default => 'primary',
                                                    })
                                                    ->icon(fn (string $state): Heroicon => match ($state) {
                                                        'published' => Heroicon::CheckCircle,
                                                        'draft' => Heroicon::PencilSquare,
                                                        'archived' => Heroicon::ArchiveBox,
                                                        default => Heroicon::QuestionMarkCircle,
                                                    }),
                                                IconEntry::make('is_public')
                                                    ->label('Public')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::Eye)
                                                    ->falseIcon(Heroicon::EyeSlash)
                                                    ->trueColor('success')
                                                    ->falseColor('danger')
                                                    ->tooltip(fn ($state): string => $state ? 'Article public' : 'Article privé'),
                                            ]),
                                    ]),
                            ]),

                        // Deuxième colonne
                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                // Section Image
                                Section::make('Image de l\'Article')
                                    ->schema([
                                        ImageEntry::make('image')
                                            ->label('')
                                            ->imageSize(200)
                                            ->alignment(Alignment::Center)
                                            ->defaultImageUrl(fn ($record): string =>
                                                $record->image
                                                    ? asset('storage/' . $record->image)
                                                    : asset('images/404.svg')
                                            ),
                                    ]),

                                // Section Informations Techniques
                                Section::make('Informations Techniques')
                                    ->description('Métadonnées et informations système.')
                                    ->schema([
                                        TextEntry::make('created_at')
                                            ->label('Créé le')
                                            ->dateTime('d/m/Y H:i')
                                            ->icon(Heroicon::Calendar)
                                            ->placeholder('Non renseigné'),
                                        TextEntry::make('updated_at')
                                            ->label('Modifié le')
                                            ->dateTime('d/m/Y H:i')
                                            ->icon(Heroicon::Clock)
                                            ->placeholder('Non renseigné'),
                                        TextEntry::make('deleted_at')
                                            ->label('Supprimé le')
                                            ->dateTime('d/m/Y H:i')
                                            ->icon(Heroicon::Trash)
                                            ->color('danger')
                                            ->placeholder('Non supprimé')
                                            ->hidden(fn ($record) => !$record->deleted_at),
                                    ]),

                                // Section Statistiques (optionnel)
                                Section::make('Statistiques')
                                    ->description('Données d\'engagement et de performance.')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('views_count')
                                                    ->label('Vues')
                                                    ->numeric()
                                                    ->icon(Heroicon::Eye)
                                                    ->placeholder('0')
                                                    ->hidden(fn ($record) => !method_exists($record, 'views_count')),
                                                TextEntry::make('likes_count')
                                                    ->label('Likes')
                                                    ->numeric()
                                                    ->icon(Heroicon::Heart)
                                                    ->placeholder('0')
                                                    ->hidden(fn ($record) => !method_exists($record, 'likes_count')),
                                            ]),
                                    ])
                                    ->hidden(fn ($record) => 
                                        !method_exists($record, 'views_count') && 
                                        !method_exists($record, 'likes_count')
                                    ),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}