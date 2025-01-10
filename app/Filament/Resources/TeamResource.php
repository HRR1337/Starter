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

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static ?string $navigationLabel = 'Tenants';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static bool $isScopedToTenant = false;

    public static function getNavigationGroup(): ?string
    {
        return __('filament-shield::filament-shield.nav.group');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignorable: fn ($record) => $record),
                Forms\Components\TextInput::make('description')
                    ->maxLength(255)
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
            ])
            ->filters([
                //
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
