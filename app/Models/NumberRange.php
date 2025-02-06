<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NumberRange extends Model
{
    protected $fillable = [
        'team_id',
        'start_number',
        'end_number',
        'parent_id',
        'description',
        'created_by',
    ];

    protected $appends = ['range_start', 'range_end'];

    /**
     * Convert range number to actual number when saving.
     */
    public function setRangeStartAttribute($value)
    {
        $this->attributes['start_number'] = ($value * 1000) + 1;
    }

    public function setRangeEndAttribute($value)
    {
        $this->attributes['end_number'] = $value * 1000;
    }

    /**
     * Convert actual number to range number for display.
     */
    public function getRangeStartAttribute()
    {
        return floor($this->start_number / 1000);
    }

    public function getRangeEndAttribute()
    {
        return floor($this->end_number / 1000);
    }

    /**
     * Relationship: Number range belongs to a team.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Relationship: Number range is created by a user.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Parent range (if this is a sub-range).
     */

     public function parent(): BelongsTo
     {
         return $this->belongsTo(NumberRange::class, 'parent_id')->withDefault([
             'name' => '(No Parent)', // Zorgt ervoor dat null een standaardwaarde krijgt
         ]);
     }

    /**
     * Relationship: Children ranges (sub-ranges that derive from this range).
     */
    public function children(): HasMany
    {
        return $this->hasMany(NumberRange::class, 'parent_id');
    }

    /**
     * Check if a given range is within its parent range.
     */
    public function isWithinParent(): bool
    {
        if (!$this->parent) {
            return true; // No parent, no restriction
        }

        return $this->start_number >= $this->parent->start_number &&
               $this->end_number <= $this->parent->end_number;
    }

    /**
     * Check if the current range overlaps with existing sub-ranges.
     */
    public function overlapsWithExistingSubranges(): bool
    {
        return self::where('parent_id', $this->parent_id)
            ->where(function ($query) {
                $query->whereBetween('start_number', [$this->start_number, $this->end_number])
                      ->orWhereBetween('end_number', [$this->start_number, $this->end_number])
                      ->orWhere(function ($q) {
                          $q->where('start_number', '<=', $this->start_number)
                            ->where('end_number', '>=', $this->end_number);
                      });
            })
            ->where('id', '!=', $this->id) // Ignore self when checking updates
            ->exists();
    }
}