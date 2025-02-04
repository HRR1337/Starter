<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NumberRangeResource\Pages;
use App\Models\NumberRange;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use App\Services\NumberRangeService;

class NumberRangeResource extends Resource
{
    protected static ?string $model = NumberRange::class;

    protected static ?string $navigationIcon = 'heroicon-o-hashtag';

    protected static ?string $navigationGroup = 'Settings';

    protected static bool $isScopedToTenant = false;

    public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Select::make('team_id')
                ->relationship('team', 'name')
                ->required(),

            Forms\Components\Grid::make()
                ->schema([
                    Forms\Components\TextInput::make('range_start')
                        ->label('Start Range')
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->step(1),

                    Forms\Components\TextInput::make('range_end')
                        ->label('End Range')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->step(1),
                ])
                ->columns(2),

            Forms\Components\TextInput::make('description')
                ->maxLength(255),
        ]);
}

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('team.name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('range_start')
                    ->label('Box Start'),
                Tables\Columns\TextColumn::make('range_end')
                    ->label('Box End'),
                Tables\Columns\TextColumn::make('start_number')
                    ->label('Start Number')
                    ->formatStateUsing(fn ($record) => number_format($record->start_number))
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_number')
                    ->label('End Number')
                    ->formatStateUsing(fn ($record) => number_format($record->end_number))
                    ->sortable(),
               // Tables\Columns\TextColumn::make('description')
                //    ->searchable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (NumberRange $record) => auth()->user()->can('update', $record)),
    
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (NumberRange $record) => auth()->user()->can('delete', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->hasRole('super_admin')),
                ]),
            ]);
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
            'index' => Pages\ListNumberRanges::route('/'),
            'create' => Pages\CreateNumberRange::route('/create'),
            'edit' => Pages\EditNumberRange::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()->hasRole('super_admin')) {
            return $query;
        }

        $userTeamIds = auth()->user()->teams->flatMap(fn ($team) => $team->getAllDescendants()->prepend($team->id));

        return $query->whereIn('team_id', $userTeamIds);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        app(NumberRangeService::class)->validateRange($data);
        return $data;
    }


}
