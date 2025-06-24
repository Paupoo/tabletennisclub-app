<?php

namespace App\Filament\Resources\Seasons\Pages;

use App\Filament\Resources\Seasons\SeasonResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSeason extends ViewRecord
{
    protected static string $resource = SeasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
