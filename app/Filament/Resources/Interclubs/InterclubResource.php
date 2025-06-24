<?php

namespace App\Filament\Resources\Interclubs;

use App\Filament\Resources\Interclubs\Pages\CreateInterclub;
use App\Filament\Resources\Interclubs\Pages\EditInterclub;
use App\Filament\Resources\Interclubs\Pages\ListInterclubs;
use App\Filament\Resources\Interclubs\Pages\ViewInterclub;
use App\Filament\Resources\Interclubs\Schemas\InterclubForm;
use App\Filament\Resources\Interclubs\Schemas\InterclubInfolist;
use App\Filament\Resources\Interclubs\Tables\InterclubsTable;
use App\Models\Interclub;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InterclubResource extends Resource
{
    protected static ?string $model = Interclub::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Bolt;

    protected static string | UnitEnum | null $navigationGroup = 'Interclubs';

    public static function form(Schema $schema): Schema
    {
        return InterclubForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InterclubInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InterclubsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInterclubs::route('/'),
            'create' => CreateInterclub::route('/create'),
            'view' => ViewInterclub::route('/{record}'),
            'edit' => EditInterclub::route('/{record}/edit'),
        ];
    }
}
