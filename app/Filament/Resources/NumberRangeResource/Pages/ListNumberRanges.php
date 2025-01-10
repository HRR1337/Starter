<?php

namespace App\Filament\Resources\NumberRangeResource\Pages;

use App\Filament\Resources\NumberRangeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNumberRanges extends ListRecords
{
    protected static string $resource = NumberRangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
