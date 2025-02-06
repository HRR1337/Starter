<?php

namespace App\Filament\Resources\NumberRangeResource\Pages;

use App\Filament\Resources\NumberRangeResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\NumberRangeService;
use Filament\Notifications\Notification;

class CreateNumberRange extends CreateRecord
{
    protected static string $resource = NumberRangeResource::class;

    protected function beforeCreate(): void
    {
        try {
            app(NumberRangeService::class)->validateRange($this->data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('Validation Error')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }
}