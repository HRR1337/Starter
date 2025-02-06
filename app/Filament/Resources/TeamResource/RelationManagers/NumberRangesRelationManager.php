<?php

namespace App\Filament\Resources\TeamResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Services\NumberRangeService;
use App\Models\NumberRange;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Model;

class NumberRangesRelationManager extends RelationManager
{
    protected static string $relationship = 'numberRanges';

    protected static ?string $title = 'Number Ranges';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('parent_id')
                    ->label('Parent Range (Optional)')
                    ->relationship('parent', 'description')
                    ->getOptionLabelFromRecordUsing(fn ($record) => 
                        sprintf(
                            '%s (Range: %d-%d)', 
                            $record->description ?? 'No Description',
                            $record->range_start,
                            $record->range_end
                        )
                    )
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Select a parent range if this is a sub-range'),

                Forms\Components\Grid::make()
                    ->schema([
                        Forms\Components\TextInput::make('range_start')
                            ->label('Start Range')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(1)
                            ->helperText('Enter the starting box number'),

                        Forms\Components\TextInput::make('range_end')
                            ->label('End Range')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->step(1)
                            ->helperText('Enter the ending box number'),
                    ])
                    ->columns(2),

                Forms\Components\TextInput::make('description')
                    ->maxLength(255)
                    ->helperText('Optional description for this range'),

                Forms\Components\Hidden::make('created_by')
                    ->default(fn () => auth()->id())
                    ->dehydrated(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('parent.description')
                    ->label('Parent Range')
                    ->description(fn (NumberRange $record) => 
                        $record->parent 
                            ? "Range: {$record->parent->range_start}-{$record->parent->range_end}" 
                            : null
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('range_start')
                    ->label('Box Start')
                    ->sortable(false),

                Tables\Columns\TextColumn::make('range_end')
                    ->label('Box End')
                    ->sortable(false),

                Tables\Columns\TextColumn::make('start_number')
                    ->label('Start Number')
                    ->formatStateUsing(fn ($record) => number_format($record->start_number))
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_number')
                    ->label('End Number')
                    ->formatStateUsing(fn ($record) => number_format($record->end_number))
                    ->sortable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->wrap(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data) {
                        try {
                            $data['team_id'] = $this->getOwnerRecord()->id;
                            
                            // Validate and create using the service
                            return app(NumberRangeService::class)->create($data);
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Validation Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            throw $e;
                        }
                    })
                    ->visible(fn () => auth()->user()->hasRole(['super_admin', 'team_admin'])),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(function (NumberRange $record, array $data) {
                        try {
                            $data['team_id'] = $this->getOwnerRecord()->id;
                            app(NumberRangeService::class)->update($record, $data);
                            
                            Notification::make()
                                ->title('Success')
                                ->body('Range updated successfully')
                                ->success()
                                ->send();

                            return $record;
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Validation Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            throw $e;
                        }
                    })
                    ->visible(fn ($record) => auth()->user()->can('update', $record)),

                Tables\Actions\DeleteAction::make()
                    ->using(function (NumberRange $record) {
                        try {
                            app(NumberRangeService::class)->delete($record);
                            
                            Notification::make()
                                ->title('Success')
                                ->body('Range deleted successfully')
                                ->success()
                                ->send();

                            return true;
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Deletion Error')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            return false;
                        }
                    })
                    ->visible(fn ($record) => auth()->user()->can('delete', $record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->hasRole('super_admin')),
                ]),
            ])
            ->defaultSort('start_number', 'asc');
    }

    protected function canCreate(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'team_admin']);
    }

    protected function canEdit(Model $record): bool
    {
        return auth()->user()->can('update', $record);
    }

    protected function canDelete(Model $record): bool
    {
        return auth()->user()->can('delete', $record);
    }
}
