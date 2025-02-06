<?php

namespace App\Filament\Resources\NumberRangeResource\Pages;

use App\Filament\Resources\NumberRangeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Services\NumberRangeService;

class EditNumberRange extends EditRecord
{
    protected static string $resource = NumberRangeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        app(NumberRangeService::class)->update($this->record, $this->form->getState());
    }
}