<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'created_by',
        'parent_id',
        'type',
        'level',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Add boot method for setting the level
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($team) {
            if ($team->parent_id) {
                // Update the level based on the parent's level
                $parentTeam = Team::find($team->parent_id);
                $team->level = $parentTeam ? $parentTeam->level + 1 : 0;
            } else {
                // Root level team
                $team->level = 0;
            }
        });
    }

    // Helper method to get all descendants
    public function getDescendants($teamId, &$descendants)
    {
        $childTeams = Team::where('parent_id', $teamId)->get();
        
        foreach ($childTeams as $child) {
            $descendants->push($child->id);
            $this->getDescendants($child->id, $descendants);
        }
    }

    // Existing relationships
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function numberRanges(): HasMany
    {
        return $this->hasMany(NumberRange::class);
    }

    // Hierarchy relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Team::class, 'parent_id');
    }

    // Helper methods for hierarchy
    public function allChildren()
    {
        return $this->children()->with('children');
    }

    public function ancestors()
    {
        return $this->parent()->with('parent');
    }

    // Get all descendants as a flat collection
    public function getAllDescendants(): \Illuminate\Support\Collection
    {
        $descendants = collect();
        $this->getDescendants($this->id, $descendants);
        return $descendants;
    }

    // Get all ancestors as a collection
    public function getAllAncestors(): \Illuminate\Support\Collection
    {
        $ancestors = collect();
        $parent = $this->parent;
        
        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    // Scopes
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeRootLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    // Helper methods
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function hasChildren(): bool
    {
        return $this->children()->count() > 0;
    }

    public function getFullHierarchyAttribute(): string
    {
        $hierarchy = collect([$this->name]);
        $parent = $this->parent;

        while ($parent) {
            $hierarchy->prepend($parent->name);
            $parent = $parent->parent;
        }

        return $hierarchy->join(' > ');
    }

    // Get the depth level in the hierarchy
    public function getDepthLevel(): int
    {
        return $this->level;
    }

    // Check if this team is a descendant of another team
    public function isDescendantOf(Team $team): bool
    {
        return $this->getAllAncestors()->contains($team);
    }

    // Check if this team is an ancestor of another team
    public function isAncestorOf(Team $team): bool
    {
        return $team->getAllAncestors()->contains($this);
    }

    // Get siblings (other teams with the same parent)
    public function getSiblings()
    {
        return Team::where('parent_id', $this->parent_id)
            ->where('id', '!=', $this->id)
            ->get();
    }

    // Move team to a new parent
    public function moveTo(?Team $newParent = null)
    {
        $this->parent_id = $newParent ? $newParent->id : null;
        $this->save(); // This will trigger the validation in boot()
    }
}
