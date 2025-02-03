<?php

namespace App\Filament\Resources\NumberRangeResource\Pages;

use App\Filament\Resources\NumberRangeResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateNumberRange extends CreateRecord
{
    protected static string $resource = NumberRangeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        
        // Team admin mag alleen NumberRanges maken voor zijn eigen team
        if (auth()->user()->hasRole('team_admin')) {
            $data['team_id'] = Filament::getTenant()?->id ?? auth()->user()->teams->first()->id;
        }
    
        return $data;
    }
}