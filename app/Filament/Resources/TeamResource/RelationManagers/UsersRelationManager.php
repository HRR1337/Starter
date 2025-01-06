<?php

namespace App\Filament\Resources\TeamResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\RelationManagers\RelationManager;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create')
                    ->maxLength(255),
                // Alleen super_admin kan roles toewijzen
                Forms\Components\Select::make('roles')
                    ->relationship('roles', 'name', function($query) {
                        if(auth()->user()->hasRole('super_admin')) {
                            return $query;
                        }
                        // Team admin kan alleen team_member rol toewijzen
                        return $query->where('name', 'team_member');
                    })
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->visible(fn () => auth()->user()->hasRole(['super_admin', 'team_admin'])),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // If not super_admin, exclude super_admin users
                if (!auth()->user()->hasRole('super_admin')) {
                    $query->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin'));
                }
                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->label(__('Role'))
                    ->colors(['primary'])
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Zorg ervoor dat team_member rol automatisch wordt toegewezen bij het aanmaken door team_admin
        if (auth()->user()->hasRole('team_admin') && !isset($data['roles'])) {
            $teamMemberRole = \Spatie\Permission\Models\Role::where('name', 'team_member')->first();
            if ($teamMemberRole) {
                $data['roles'] = [$teamMemberRole->id];
            }
        }

        // Koppel de gebruiker aan het team
        $data['teams'] = [$this->ownerRecord->id];

        return $data;
    }
}