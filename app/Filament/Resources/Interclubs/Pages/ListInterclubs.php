<?php

namespace App\Filament\Resources\Interclubs\Pages;

use App\Filament\Resources\Interclubs\InterclubResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInterclubs extends ListRecords
{
    protected static string $resource = InterclubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
