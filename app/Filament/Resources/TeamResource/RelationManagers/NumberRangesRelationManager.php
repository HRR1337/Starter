<?php

namespace App\Filament\Resources\TeamResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Filament\Resources\RelationManagers\RelationManager;
use App\Services\NumberRangeService;

class NumberRangesRelationManager extends RelationManager
{
    protected static string $relationship = 'numberRanges';

    protected static ?string $title = 'Number Ranges';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Form $form): Form
{
    return $form
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

            Forms\Components\TextInput::make('description')
                ->maxLength(255),
        ]);
}

    public function table(Table $table): Table
    {
        return $table
            ->columns([
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

    protected function mutateFormDataBeforeSave(array $data): array
{
    app(NumberRangeService::class)->validateRange($data, $this->record ?? null);
    return $data;
}

protected function afterSave(): void
{
    app(NumberRangeService::class)->update($this->record, $this->form->getState());
}
}