<?php

namespace App\Filament\Resources\NumberRangeResource\Pages;

use App\Filament\Resources\NumberRangeResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use App\Services\NumberRangeService;

class CreateNumberRange extends CreateRecord
{
    protected static string $resource = NumberRangeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(NumberRangeService::class)->create($data)->toArray();
    }
}