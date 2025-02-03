<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumberRange extends Model
{
    protected $fillable = [
        'team_id',
        'start_number',
        'end_number',
        'description',
        'created_by'
    ];

    protected $appends = ['range_start', 'range_end'];

    // Convert range number to actual number when saving
    public function setRangeStartAttribute($value)
    {
        $this->attributes['start_number'] = ($value * 1000) + 1;
    }

    public function setRangeEndAttribute($value)
    {
        $this->attributes['end_number'] = $value * 1000;
    }

    // Convert actual number to range number for display
    public function getRangeStartAttribute()
    {
        return floor($this->start_number / 1000);
    }

    public function getRangeEndAttribute()
    {
        return floor($this->end_number / 1000);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
