<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Models\Team;
use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Filter alleen de user-gerelateerde velden
        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ];
    }

    protected function afterCreate(): void
    {
        $user = $this->record;
        
        // Koppel bestaande teams
        if (!empty($this->data['teams'])) {
            $user->teams()->attach($this->data['teams']);
        }

        // Maak en koppel nieuw team
        if (!empty($this->data['create_new_team']) && !empty($this->data['new_team_name'])) {
            $team = Team::create([
                'name' => $this->data['new_team_name'],
                'slug' => $this->data['new_team_slug'],
                'created_by' => auth()->id(),
            ]);
            
            $user->teams()->attach($team->id);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
