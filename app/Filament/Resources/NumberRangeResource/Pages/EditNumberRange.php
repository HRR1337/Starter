<?php

namespace App\Filament\Resources\NumberRangeResource\Pages;

use App\Filament\Resources\NumberRangeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNumberRange extends EditRecord
{
    protected static string $resource = NumberRangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
