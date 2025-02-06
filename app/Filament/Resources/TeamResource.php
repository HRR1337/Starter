<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Filament\Resources\TeamResource\RelationManagers;
use App\Models\Team;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use App\Rules\ValidTeamHierarchy;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static ?string $navigationLabel = 'Teams';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static bool $isScopedToTenant = false;

    public static function getNavigationGroup(): ?string
    {
        return __('filament-shield::filament-shield.nav.group');
    }

    public static function form(Form $form): Form
    {
        $user = auth()->user();
        $currentTeam = Filament::getTenant();
    
        return $form
            ->schema([
                Forms\Components\Section::make('Team Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignorable: fn ($record) => $record),
                        Forms\Components\TextInput::make('description')
                            ->maxLength(255),
                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Team')
                            ->relationship('parent', 'name', function ($query) use ($user) {
                                // Voor team_admin, alleen teams waar ze lid van zijn
                                if ($user->hasRole('team_admin')) {
                                    return $query->whereIn('id', $user->teams->pluck('id'));
                                }
                                return $query;
                            })
                            ->searchable()
                            ->preload()
                            ->default(fn () => $user->hasRole('team_admin') ? $currentTeam?->id : null)
                            ->required(fn () => $user->hasRole('team_admin'))
                            ->disabled(fn () => $user->hasRole('team_admin') && $user->teams->count() === 1)
                            ->visible(fn () => $user->hasRole(['super_admin', 'team_admin']))
                            ->rules([
                                fn ($get) => new ValidTeamHierarchy($get('id')),
                            ]),
                        Forms\Components\Select::make('type')
                            ->label('Team Type')
                            ->options([
                                'department' => 'Department',
                                'division' => 'Division',
                                'team' => 'Team',
                                'unit' => 'Unit',
                            ])
                            ->default('team')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //Tables\Columns\TextColumn::make('name')
                //    ->searchable(),
                Tables\Columns\TextColumn::make('full_hierarchy')
                    ->label('Team')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'department' => 'Department',
                        'division' => 'Division',
                        'team' => 'Team',
                        'unit' => 'Unit',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Team $record) =>
                        auth()->user()->hasRole('super_admin') ||
                        $record->created_by === auth()->id()
                    ),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Team $record) =>
                        auth()->user()->hasRole('super_admin') ||
                        ($record->created_by === auth()->id() && $record->id !== Filament::getTenant()->id)
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->hasRole('super_admin')),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Super admin can see all teams
        if (auth()->user()->hasRole('super_admin')) {
            return $query;
        }

        // Team admin can see their teams and teams they created
        if (auth()->user()->hasRole('team_admin')) {
            return $query->where(function ($query) {
                $query->whereIn('id', auth()->user()->teams->pluck('id'))
                    ->orWhere('created_by', auth()->id());
            });
        }

        // Team members can only see teams they belong to
        return $query->whereHas('users', function ($query) {
            $query->where('users.id', auth()->user()->id);
        });
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\NumberRangesRelationManager::class,
            RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'team_admin']);
    }
}
