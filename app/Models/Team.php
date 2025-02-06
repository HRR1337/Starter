<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * Boot method for setting hierarchy level automatically.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($team) {
            $team->level = $team->parent ? $team->parent->level + 1 : 0;
        });
    }

    /**
     * Get direct parent of the team.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'parent_id');
    }

    /**
     * Get direct children of the team.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Team::class, 'parent_id')->with('children');
    }

    /**
     * Get all descendants recursively.
     */
    public function descendants(): HasMany
    {
        return $this->hasMany(Team::class, 'parent_id')->with('descendants');
    }

    /**
     * Get all ancestors recursively.
     */
    public function ancestors()
    {
        return $this->parent()->with('ancestors');
    }

    /**
     * Get all descendants as a flat collection using a Recursive CTE.
     */
    public function getAllDescendants(): Collection
    {
        $results = DB::select("
            WITH RECURSIVE team_tree AS (
                SELECT id, parent_id FROM teams WHERE id = ?
                UNION ALL
                SELECT t.id, t.parent_id FROM teams t
                INNER JOIN team_tree tt ON t.parent_id = tt.id
            )
            SELECT * FROM team_tree WHERE id != ?
        ", [$this->id, $this->id]);
    
        return collect($results); 
    }

    /**
     * Get all ancestors as a flat collection.
     */
    public function getAllAncestors(): Collection
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Scope to only active teams.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope to only root-level teams.
     */
    public function scopeRootLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * Scope teams by type.
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Check if this team is a root team.
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if this team has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get full hierarchy string (e.g., "Company > Division > Department").
     */
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

    /**
     * Get the depth level in the hierarchy.
     */
    public function getDepthLevel(): int
    {
        return $this->level;
    }

    /**
     * Check if this team is a descendant of another team.
     */
    public function isDescendantOf(Team $team): bool
    {
        return $this->getAllAncestors()->contains($team);
    }

    /**
     * Check if this team is an ancestor of another team.
     */
    public function isAncestorOf(Team $team): bool
    {
        return $team->getAllAncestors()->contains($this);
    }

    /**
     * Get siblings (other teams with the same parent).
     */
    public function getSiblings(): Collection
    {
        return Team::where('parent_id', $this->parent_id)
            ->where('id', '!=', $this->id)
            ->get();
    }

    /**
     * Move team to a new parent.
     */
    public function moveTo(?Team $newParent = null)
    {
        $this->parent_id = $newParent ? $newParent->id : null;
        $this->save();
    }

    public function users(): BelongsToMany
{
    return $this->belongsToMany(User::class)->withTimestamps();
}

public function numberRanges(): HasMany
{
    return $this->hasMany(NumberRange::class, 'team_id');
}

public function createdBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'created_by');
}
}