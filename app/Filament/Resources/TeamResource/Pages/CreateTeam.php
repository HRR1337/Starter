<?php

namespace App\Filament\Resources\TeamResource\Pages;

use App\Models\Team;
use App\Models\User;
use Filament\Actions;
use App\Traits\GenerateSlug;
use App\Filament\Resources\TeamResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateTeam extends CreateRecord
{
    use GenerateSlug;
    
    protected static string $resource = TeamResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $currentTeam = Filament::getTenant();
    
        $data['slug'] = $this->generateSlug($data['name'], 'teams');
        $data['created_by'] = auth()->id();
    
        // Als het een team_admin is en er is nog geen parent_id ingesteld
        if ($user->hasRole('team_admin') && !isset($data['parent_id'])) {
            // Als de admin maar één team heeft, gebruik dat als parent
            if ($user->teams->count() === 1) {
                $data['parent_id'] = $user->teams->first()->id;
            } else {
                // Anders gebruik het huidige team als parent
                $data['parent_id'] = $currentTeam->id;
            }
        }
    
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $this->record->users()->attach(auth()->user());
    }
}
