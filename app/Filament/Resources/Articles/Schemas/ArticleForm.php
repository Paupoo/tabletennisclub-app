<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Enums\ArticlesCategoryEnum;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Str;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->afterStateUpdated(function ($get, $set, $state) {
                        if (! $get('is_slug_changed_manually') && filled($state)) {
                            $set('slug', Str::slug($state));
                        }
                    })
                    ->reactive(),
                TextInput::make('slug')
                    ->afterStateUpdated(function ($set) {
                        $set('is_slug_changed_manually', true);
                    })
                    ->required()
                    ->disabled()
                    ->dehydrated(),
                Hidden::make('is_slug_changed_manually')
                    ->default(false)
                    ->dehydrated(false),
                MarkdownEditor::make('content')
                    ->required()
                    ->columnSpanFull(),
                Select::make('category')
                    ->options(ArticlesCategoryEnum::class)
                    ->required(),
                FileUpload::make('image')
                    ->image()
                    ->directory('articles/images')
                    ->visibility('public')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'id')
                    ->required()
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->first_name} {$record->last_name}")
                    ->searchable(['first_name', 'last_name'])
                    ->preload(),
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
