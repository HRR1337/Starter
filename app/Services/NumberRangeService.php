<?php

namespace App\Services;

use App\Models\NumberRange;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Filament\Notifications\Notification;

class NumberRangeService
{
    /**
     * Validate the number range before saving.
     */
    public function validateRange(array $data, ?NumberRange $record = null): void
    {
        // Basis validatie: Start moet kleiner zijn dan End
        if ($data['range_end'] <= $data['range_start']) {
            throw ValidationException::withMessages([
                'range_end' => "End range ({$data['range_end']}) must be greater than start range ({$data['range_start']})."
            ]);
        }

        $startNumber = ($data['range_start'] * 1000) + 1;
        $endNumber = $data['range_end'] * 1000;

        // Haal het team op waarvoor we de range maken
        $team = Team::findOrFail($data['team_id']);

        // ✅ 1. Controleer of het team al een bestaande range heeft die overlapt
        $existingRanges = NumberRange::where('team_id', $data['team_id'])
            ->where('id', '!=', $record->id ?? null) // Sluit eigen ID uit bij update
            ->where(function ($query) use ($startNumber, $endNumber) {
                $query->where(function ($q) use ($startNumber, $endNumber) {
                    $q->where('start_number', '<=', $startNumber)
                      ->where('end_number', '>=', $startNumber);
                })->orWhere(function ($q) use ($startNumber, $endNumber) {
                    $q->where('start_number', '<=', $endNumber)
                      ->where('end_number', '>=', $endNumber);
                })->orWhere(function ($q) use ($startNumber, $endNumber) {
                    $q->where('start_number', '>=', $startNumber)
                      ->where('end_number', '<=', $endNumber);
                });
            })
            ->exists();

        if ($existingRanges) {
            throw ValidationException::withMessages([
                'range_start' => "Your range ({$data['range_start']}-{$data['range_end']}) overlaps with an existing range for the same team."
            ]);
        }

        // ✅ 2. Controleer of de range binnen de parent range valt (als er een parent is)
        if ($team->parent_id) {
            // Fetch ALL number ranges for the parent team
            $parentTeamRanges = NumberRange::where('team_id', $team->parent_id)->get();
            
            $isWithinParentRange = false;
        
            foreach ($parentTeamRanges as $parentRange) {
                if ($startNumber >= $parentRange->start_number && $endNumber <= $parentRange->end_number) {
                    $isWithinParentRange = true;
                    break;
                }
            }
        
            if (!$isWithinParentRange) {
                throw ValidationException::withMessages([
                    'range_start' => sprintf(
                        'Your range (%d-%d) must be within at least one of your parent team\'s ranges: %s',
                        $data['range_start'],
                        $data['range_end'],
                        $parentTeamRanges->map(fn ($r) => sprintf('%d-%d', floor($r->start_number / 1000), floor($r->end_number / 1000)))->implode(', ')
                    )
                ]);
            }
        }

        // ✅ 3. Controleer overlapping met sibling teams (zelfde hiërarchie)
        $siblingTeamIds = Team::where(function ($query) use ($team) {
            if ($team->parent_id) {
                $query->where('parent_id', $team->parent_id);
            } else {
                $query->whereNull('parent_id');
            }
        })->where('id', '!=', $team->id)
          ->pluck('id');

        $overlappingSiblingRanges = NumberRange::whereIn('team_id', $siblingTeamIds)
            ->where(function ($query) use ($startNumber, $endNumber) {
                $query->where(function ($q) use ($startNumber, $endNumber) {
                    $q->where('start_number', '<=', $startNumber)
                      ->where('end_number', '>=', $startNumber);
                })->orWhere(function ($q) use ($startNumber, $endNumber) {
                    $q->where('start_number', '<=', $endNumber)
                      ->where('end_number', '>=', $endNumber);
                })->orWhere(function ($q) use ($startNumber, $endNumber) {
                    $q->where('start_number', '>=', $startNumber)
                      ->where('end_number', '<=', $endNumber);
                });
            });

        if ($record) {
            $overlappingSiblingRanges->where('id', '!=', $record->id);
        }

        $overlapping = $overlappingSiblingRanges->get();

        if ($overlapping->isNotEmpty()) {
            $messages = [];
            foreach ($overlapping as $range) {
                $messages[] = sprintf(
                    'Your range (%d-%d) overlaps with existing range %d-%d from team %s',
                    $data['range_start'],
                    $data['range_end'],
                    floor($range->start_number/1000),
                    floor($range->end_number/1000),
                    $range->team->name
                );
            }
            
            throw ValidationException::withMessages([
                'range_start' => $messages[0]
            ]);
        }
    }

    /**
     * Create a new number range.
     */
    public function create(array $data): NumberRange
    {
        try {
            if (!isset($data['created_by'])) {
                $data['created_by'] = auth()->id();
            }

            $this->validateRange($data);
            
            return DB::transaction(function () use ($data) {
                return NumberRange::create([
                    'team_id' => $data['team_id'],
                    'parent_id' => $data['parent_id'] ?? null,
                    'start_number' => ($data['range_start'] * 1000) + 1,
                    'end_number' => $data['range_end'] * 1000,
                    'description' => $data['description'] ?? null,
                    'created_by' => $data['created_by'],
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    /**
     * Update an existing number range.
     */
    public function update(NumberRange $numberRange, array $data): void
    {
        try {
            $this->validateRange($data, $numberRange);

            DB::transaction(function () use ($numberRange, $data) {
                $numberRange->update([
                    'parent_id' => $data['parent_id'] ?? null,
                    'start_number' => ($data['range_start'] * 1000) + 1,
                    'end_number' => $data['range_end'] * 1000,
                    'description' => $data['description'] ?? null,
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    /**
     * Delete a number range.
     */
    public function delete(NumberRange $numberRange): void
    {
        if ($numberRange->children()->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'Cannot delete a range that has sub-ranges.'
            ]);
        }

        DB::transaction(function () use ($numberRange) {
            $numberRange->delete();
        });
    }
}