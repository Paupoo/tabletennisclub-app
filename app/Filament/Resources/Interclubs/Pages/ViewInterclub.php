<?php

namespace App\Filament\Resources\Interclubs\Pages;

use App\Filament\Resources\Interclubs\InterclubResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInterclub extends ViewRecord
{
    protected static string $resource = InterclubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
