<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Models\Team;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\UserResource\Pages;
use Illuminate\Support\Str;
use Filament\Facades\Filament;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static bool $isScopedToTenant = false;

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
                        Forms\Components\Select::make('roles')
                            ->relationship('roles', 'name', function($query) {
                                if(auth()->user()->hasRole('super_admin')) {
                                    return $query;
                                }
                                return $query->where('name', 'team_member');
                            })
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->visible(fn () => auth()->user()->hasRole(['super_admin', 'team_admin'])),
                    ])->columns(2),
                Forms\Components\Section::make(__('Tenant'))
                    ->description('Select an existing team or create a new one.')
                    ->schema([
                        Forms\Components\Grid::make()
                            ->schema([
                                Forms\Components\Select::make('teams')
                                    ->label('Existing Teams')
                                    ->relationship('teams', 'name', function($query) {
                                        if(auth()->user()->hasRole('super_admin')) {
                                            return $query;
                                        }
                                        return $query->whereIn('teams.id', auth()->user()->teams->pluck('id'));
                                    })
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),
                                
                                Forms\Components\Toggle::make('create_new_team')
                                    ->label('Create New Team')
                                    ->reactive()
                                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
                                
                                Forms\Components\TextInput::make('new_team_name')
                                    ->label('New Team Name')
                                    ->visible(fn (callable $get) => $get('create_new_team'))
                                    ->required(fn (callable $get) => $get('create_new_team'))
                                    ->unique(Team::class, 'name')
                                    ->rules(['required_if:create_new_team,true'])
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('new_team_slug', Str::slug($state));
                                    }),
                                
                                Forms\Components\TextInput::make('new_team_slug')
                                    ->label('Team Slug')
                                    ->visible(fn (callable $get) => $get('create_new_team'))
                                    ->disabled()
                                    ->dehydrated(fn (callable $get) => $get('create_new_team')),
                            ]),
                    ]),
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
    
        // Super admin can see all users
        if (auth()->user()->hasRole('super_admin')) {
            return $query;
        }
    
        // Team admin can see users in their teams except super admins
        if (auth()->user()->hasRole('team_admin')) {
            $teamIds = auth()->user()->teams->pluck('id');
            return $query
                ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin'))
                ->whereHas('teams', function ($query) use ($teamIds) {
                    $query->whereIn('teams.id', $teamIds);
                })
                ->where('users.id', '!=', auth()->id());
        }
    
        // Team members can see other users in their teams except super admins
        if (auth()->user()->hasRole('team_member')) {
            if (Filament::getTenant()) {
                return $query
                    ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin'))
                    ->whereHas('teams', function ($query) {
                        $query->where('teams.id', Filament::getTenant()->id);
                    });
            }
        }
    
        // Default: users can only see themselves
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
