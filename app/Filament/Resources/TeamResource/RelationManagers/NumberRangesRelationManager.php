<?php

namespace App\Filament\Resources\TeamResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Filament\Resources\RelationManagers\RelationManager;

class NumberRangesRelationManager extends RelationManager
{
    protected static string $relationship = 'numberRanges';

    protected static ?string $title = 'Number Ranges';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Form $form): Form
    {
        $user = auth()->user();
        $parentTeamRanges = null;

        // If team_admin, get parent team's ranges
        if ($user->hasRole('team_admin')) {
            $parentTeam = $this->getOwnerRecord()->parent;
            if ($parentTeam) {
                $parentTeamRanges = $parentTeam->numberRanges;
            }
        }

        return $form
            ->schema([
                Forms\Components\Grid::make()
                    ->schema([
                        Forms\Components\TextInput::make('range_start')
                            ->label('Start Range')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->hint('Example: 0 means 1-1000, 1 means 1001-2000')
                            ->helperText('Enter the starting range number'),

                        Forms\Components\TextInput::make('range_end')
                            ->label('End Range')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->step(1)
                            ->hint('Example: 1 means 1000, 2 means 2000')
                            ->helperText('Enter the ending range number')
                            ->rules([
                                'required',
                                'numeric',
                                'min:1',
                                function ($state) use ($parentTeamRanges) {
                                    return function ($attribute, $value, $fail) use ($state, $parentTeamRanges) {
                                        $startRange = data_get($state, 'range_start');
                                        if ($startRange === null) {
                                            return; // Let the required validation handle this
                                        }

                                        // Convert to actual numbers for comparison
                                        $startNumber = ($startRange * 1000) + 1;
                                        $endNumber = $value * 1000;

                                        if ($endNumber <= $startNumber) {
                                            $fail('End range must be greater than start range.');
                                            return;
                                        }

                                        $user = auth()->user();
                                        $team = $this->getOwnerRecord();

                                        if ($user->hasRole('team_admin') && $parentTeamRanges) {
                                            // Verify range is within parent team's ranges
                                            $isWithinParentRange = false;
                                            foreach ($parentTeamRanges as $parentRange) {
                                                if ($startNumber >= $parentRange->start_number &&
                                                    $endNumber <= $parentRange->end_number) {
                                                    $isWithinParentRange = true;
                                                    break;
                                                }
                                            }

                                            if (!$isWithinParentRange) {
                                                $fail("Range must be within parent team's allocated ranges.");
                                                return;
                                            }
                                        }

                                        // Check for overlapping ranges
                                        $query = $team->numberRanges()
                                            ->where(function ($query) use ($startNumber, $endNumber) {
                                                $query->where(function ($q) use ($startNumber) {
                                                    $q->where('start_number', '<=', $startNumber)
                                                        ->where('end_number', '>=', $startNumber);
                                                })->orWhere(function ($q) use ($endNumber) {
                                                    $q->where('start_number', '<=', $endNumber)
                                                        ->where('end_number', '>=', $endNumber);
                                                })->orWhere(function ($q) use ($startNumber, $endNumber) {
                                                    $q->where('start_number', '>=', $startNumber)
                                                        ->where('end_number', '<=', $endNumber);
                                                });
                                            });

                                        if ($team) {
                                            $query->where('team_id', $team->id);
                                        }

                                        if ($query->exists()) {
                                            $fail('This range overlaps with existing ranges.');
                                        }
                                    };
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

                // Show available parent ranges for team_admin
                Forms\Components\Placeholder::make('available_ranges')
                    ->label('Available Parent Ranges')
                    ->content(function () use ($parentTeamRanges) {
                        if ($parentTeamRanges) {
                            return $parentTeamRanges->map(function ($range) {
                                return "Range {$range->range_start}-{$range->range_end} " .
                                    "({$range->start_number}-{$range->end_number})";
                            })->join(', ');
                        }
                        return '';
                    })
                    ->visible(fn () => auth()->user()->hasRole('team_admin')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('start_number')
                    ->label('Start Number')
                    ->formatStateUsing(fn ($record) => number_format($record->start_number))
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_number')
                    ->label('End Number')
                    ->formatStateUsing(fn ($record) => number_format($record->end_number))
                    ->sortable(),
                Tables\Columns\TextColumn::make('range_start')
                    ->label('Range Start'),
                Tables\Columns\TextColumn::make('range_end')
                    ->label('Range End'),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $user = auth()->user();
                        if (!$user) {
                            abort(403, 'User is not authenticated.');
                        }

                        $data['created_by'] = $user->id;

                        // Log the data for debugging
                        Log::info('Creating Number Range:', $data);

                        return $data;
                    })
                    ->visible(fn () => auth()->user()->hasRole(['super_admin', 'team_admin'])),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => auth()->user()->can('update', $record)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => auth()->user()->can('delete', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}