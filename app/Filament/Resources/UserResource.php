<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\UserResource\Pages;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    public static function getNavigationGroup(): ?string
    {
        return __('filament-shield::filament-shield.nav.group');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('User'))
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
                    ])->columns(2),
                Forms\Components\Section::make(__('Tenant'))
                    ->description('Selecting Multi Tenancy will allow you to assign the user to a tenant.')
                    ->schema([
                        Forms\Components\Select::make('teams')
                            ->label(__('Tenant'))
                            ->relationship('teams', 'name', function($query) {
                                if(auth()->user()->hasRole('super_admin')) {
                                    return $query;
                                }
                                // Team admin ziet alleen eigen teams
                                return $query->whereIn('teams.id', auth()->user()->teams->pluck('id'));
                            })
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->required(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->label(__('Role'))
                    ->colors(['primary'])
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Impersonate::make()->visible(fn ($record) => auth()->user()->hasRole('super_admin')),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
    
        if (auth()->user()->hasRole('team_admin')) {
            $teamIds = auth()->user()->teams->pluck('id');
    
            return $query
                // Exclude super_admin users
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin'))
                // Only users in same teams
                ->whereHas('teams', function ($query) use ($teamIds) {
                    $query->whereIn('teams.id', $teamIds);
                })
                // Exclude yourself
                ->where('users.id', '!=', auth()->id());
        }
    
        if (auth()->user()->hasRole('team_member')) {
            $teamIds = auth()->user()->teams->pluck('id');
    
            return $query
                // Exclude super_admin users
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin'))
                // Only users in same teams
                ->whereHas('teams', function ($query) use ($teamIds) {
                    $query->whereIn('teams.id', $teamIds);
                });
        }
    
        if (auth()->user()->hasRole('super_admin')) {
            return $query;
        }
    
        // For all other users, only own profile
        return $query->where('users.id', auth()->id());
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
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

        return $data;
    }
}
