<?php

namespace App\Services;

use App\Models\NumberRange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class NumberRangeService
{
    /**
     * Validate the number range before saving.
     */
    public function validateRange(array $data, ?NumberRange $record = null): void
    {
        if ($data['range_end'] <= $data['range_start']) {
            throw ValidationException::withMessages([
                'range_end' => 'End range must be greater than start range.',
            ]);
        }

        $startNumber = ($data['range_start'] * 1000) + 1;
        $endNumber = $data['range_end'] * 1000;

        $query = NumberRange::where(function ($query) use ($startNumber, $endNumber) {
            $query->where(function ($q) use ($startNumber, $endNumber) {
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

        if ($record) {
            $query->where('id', '!=', $record->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'range_start' => 'This range overlaps with an existing range.',
            ]);
        }
    }

    /**
     * Create a new number range.
     */
    public function create(array $data): NumberRange
    {
        $data['created_by'] = Auth::id();

        return NumberRange::create($data);
    }

    /**
     * Update an existing number range.
     */
    public function update(NumberRange $numberRange, array $data): void
    {
        $this->validateRange($data, $numberRange);
        $numberRange->update($data);
    }

    /**
     * Delete a number range.
     */
    public function delete(NumberRange $numberRange): void
    {
        $numberRange->delete();
    }
}