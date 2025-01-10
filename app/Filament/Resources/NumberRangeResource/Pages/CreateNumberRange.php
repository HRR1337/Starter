<?php

namespace App\Filament\Resources\NumberRangeResource\Pages;

use App\Filament\Resources\NumberRangeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNumberRange extends CreateRecord
{
    protected static string $resource = NumberRangeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}