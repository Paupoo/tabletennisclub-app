<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Enums\ArticlesCategoryEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('content')
                    ->required()
                    ->columnSpanFull(),
                Select::make('category')
                    ->options(ArticlesCategoryEnum::class)
                    ->required(),
                FileUpload::make('image')
                    ->image(),
                Select::make('user_id')
                    ->relationship('user', 'id')
                    ->required(),
                TextInput::make('tags')
                    ->default(null),
                Select::make('status')
                    ->options(['Draft' => 'Draft', 'Published' => 'Published', 'Archived' => 'Archived'])
                    ->default('Draft')
                    ->required(),
                Toggle::make('is_public')
                    ->required(),
            ]);
    }
}
