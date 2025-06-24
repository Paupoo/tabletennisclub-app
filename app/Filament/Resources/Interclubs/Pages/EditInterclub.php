<?php

namespace App\Filament\Resources\Interclubs\Pages;

use App\Filament\Resources\Interclubs\InterclubResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInterclub extends EditRecord
{
    protected static string $resource = InterclubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
