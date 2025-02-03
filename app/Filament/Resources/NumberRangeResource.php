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
                    ->visible(fn () => auth()->user()->can('create', NumberRange::class))
                    ->disabled(fn () => auth()->user()->hasRole('team_admin')),

                Forms\Components\Grid::make()
                    ->schema([
                        Forms\Components\TextInput::make('range_start')
                            ->label('Start Range')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->disabled(fn () => !auth()->user()->hasRole('super_admin'))
                            ->hint('Example: 0 means 1-1000, 1 means 1001-2000')
                            ->helperText('Enter the starting range number'),

                        Forms\Components\TextInput::make('range_end')
                            ->label('End Range')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->step(1)
                            ->disabled(fn () => !auth()->user()->hasRole('super_admin'))
                            ->hint('Example: 1 means 1000, 2 means 2000')
                            ->helperText('Enter the ending range number')
                            ->rules([
                                fn ($get, $record) => function ($attribute, $value, $fail) use ($get, $record) {
                                    if ($value <= $get('range_start')) {
                                        $fail('End range must be greater than start range.');
                                        return;
                                    }

                                    // Convert range numbers naar daadwerkelijke getallen
                                    $startNumber = ($get('range_start') * 1000) + 1;
                                    $endNumber = $value * 1000;

                                    if (auth()->user()->hasRole('super_admin')) {
                                        // Super admins mogen elke range instellen, maar ze mogen niet overlappen
                                        $query = NumberRange::where(function ($query) use ($startNumber, $endNumber) {
                                            $query->where(function ($q) use ($startNumber, $endNumber) {
                                                $q->where(function ($inner) use ($startNumber) {
                                                    $inner->where('start_number', '<=', $startNumber)
                                                          ->where('end_number', '>=', $startNumber);
                                                })->orWhere(function ($inner) use ($endNumber) {
                                                    $inner->where('start_number', '<=', $endNumber)
                                                          ->where('end_number', '>=', $endNumber);
                                                })->orWhere(function ($inner) use ($startNumber, $endNumber) {
                                                    $inner->where('start_number', '>=', $startNumber)
                                                          ->where('end_number', '<=', $endNumber);
                                                });
                                            });
                                        });

                                        if ($record) {
                                            $query->where('id', '!=', $record->id);
                                        }

                                        if ($query->exists()) {
                                            $fail('This range overlaps with an existing range.');
                                        }
                                        return;
                                    }

                                    // Team admins mogen alleen sub-ranges instellen binnen een super_admin range
                                    if (auth()->user()->hasRole('team_admin')) {
                                        $userTeamIds = auth()->user()->teams->flatMap(fn ($team) => $team->getAllDescendants()->prepend($team->id));

                                        // Haal alle super_admin-ranges op
                                        $originalRanges = NumberRange::whereNull('created_by') // Alleen super_admin ranges
                                            ->get(['start_number', 'end_number']);

                                        $isWithinSuperAdminRange = $originalRanges->contains(function ($range) use ($startNumber, $endNumber) {
                                            return $range->start_number <= $startNumber && $range->end_number >= $endNumber;
                                        });

                                        if (!$isWithinSuperAdminRange) {
                                            $fail('Your range must be within an original super_admin range.');
                                            return;
                                        }

                                        // Controleer of de nieuwe range overlapt met een bestaand sub-team range
                                        $query = NumberRange::whereIn('team_id', $userTeamIds)
                                            ->where(function ($query) use ($startNumber, $endNumber) {
                                                $query->where(function ($q) use ($startNumber, $endNumber) {
                                                    $q->where(function ($inner) use ($startNumber) {
                                                        $inner->where('start_number', '<=', $startNumber)
                                                              ->where('end_number', '>=', $startNumber);
                                                    })->orWhere(function ($inner) use ($endNumber) {
                                                        $inner->where('start_number', '<=', $endNumber)
                                                              ->where('end_number', '>=', $endNumber);
                                                    })->orWhere(function ($inner) use ($startNumber, $endNumber) {
                                                        $inner->where('start_number', '>=', $startNumber)
                                                              ->where('end_number', '<=', $endNumber);
                                                    });
                                                });
                                            });

                                        if ($record) {
                                            $query->where('id', '!=', $record->id);
                                        }

                                        if ($query->exists()) {
                                            $fail('This range overlaps with an existing sub-team range.');
                                        }
                                    }
                                },
                            ]),
                    ])->columns(2),

                Forms\Components\TextInput::make('description')
                    ->maxLength(255),

                Forms\Components\Placeholder::make('actual_range')
                    ->label('Actual Number Range')
                    ->content(function ($get) {
                        if ($get('range_start') !== null && $get('range_end') !== null) {
                            $start = ($get('range_start') * 1000) + 1;
                            $end = $get('range_end') * 1000;
                            return number_format($start) . ' - ' . number_format($end);
                        }
                        return 'Enter range values to see actual numbers';
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('team.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_number')
                    ->label('Start Number')
                    ->formatStateUsing(fn ($record) => number_format($record->start_number))
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_number')
                    ->label('End Number')
                    ->formatStateUsing(fn ($record) => number_format($record->end_number))
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
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
}
