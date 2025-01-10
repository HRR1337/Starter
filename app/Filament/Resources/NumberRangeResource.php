<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NumberRangeResource\Pages;
use App\Models\NumberRange;
use Filament\Forms;
use Filament\Forms\Form;  // Change this import
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Facades\Filament;
use Filament\Tables\Table;  // Add this import
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
                    ->required()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
                Forms\Components\TextInput::make('start_number')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->disabled(fn () => !auth()->user()->hasRole('super_admin')),
                Forms\Components\TextInput::make('end_number')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->disabled(fn () => !auth()->user()->hasRole('super_admin'))
                    ->rules([
                        fn ($get, $record) => function ($attribute, $value, $fail) use ($get, $record) {
                            if ($value <= $get('start_number')) {
                                $fail('End number must be greater than start number.');
                                return;
                            }
                
                            // Check for overlapping ranges
                            $query = NumberRange::where(function ($query) use ($get, $value) {
                                $query->where(function ($q) use ($get, $value) {
                                    // Check if new range overlaps with existing ranges
                                    $q->where(function ($inner) use ($get, $value) {
                                        // New range starts within an existing range
                                        $inner->where('start_number', '<=', $get('start_number'))
                                            ->where('end_number', '>=', $get('start_number'));
                                    })->orWhere(function ($inner) use ($get, $value) {
                                        // New range ends within an existing range
                                        $inner->where('start_number', '<=', $value)
                                            ->where('end_number', '>=', $value);
                                    })->orWhere(function ($inner) use ($get, $value) {
                                        // New range completely contains an existing range
                                        $inner->where('start_number', '>=', $get('start_number'))
                                            ->where('end_number', '<=', $value);
                                    });
                                });
                            });
                
                            // Exclude current record when editing
                            if ($record) {
                                $query->where('id', '!=', $record->id);
                            }
                
                            if ($query->exists()) {
                                $fail('This range overlaps with an existing range.');
                            }
                        },
                    ]),
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
                Tables\Columns\TextColumn::make('start_number')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_number')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
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
    
        // If not super_admin, scope to current tenant
        if (!auth()->user()->hasRole('super_admin')) {
            if (Filament::getTenant()) {
                $query->where('team_id', Filament::getTenant()->id);
            }
        }
    
        return $query;
    }
}