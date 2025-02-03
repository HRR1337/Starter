<?php

namespace App\Policies;

use App\Models\User;
use App\Models\NumberRange;
use Illuminate\Auth\Access\HandlesAuthorization;

class NumberRangePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can create a number range.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'team_admin']); // Allow both super_admin and team_admin
    }

    /**
     * Determine whether the user can update the number range.
     */
    public function update(User $user, NumberRange $numberRange): bool
    {
        // Super admin mag alles
        if ($user->hasRole('super_admin')) {
            return true;
        }
    
        // Haal alle teams op waar de user team_admin van is (inclusief subteams)
        $userTeamIds = $user->teams->flatMap(fn ($team) => $team->getAllDescendants()->prepend($team->id));
    
        // Controleer of de NumberRange binnen deze teams valt
        return $user->hasRole('team_admin') && $userTeamIds->contains($numberRange->team_id);
    }

    /**
     * Determine whether the user can delete the number range.
     */
    public function delete(User $user, NumberRange $numberRange): bool
    {
        // Super admin mag alles
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Haal alle teams op waar de user team_admin van is (inclusief subteams)
        $userTeamIds = $user->teams->flatMap(fn ($team) => $team->getAllDescendants()->prepend($team->id));

        // Controleer of de NumberRange binnen deze teams valt
        return $user->hasRole('team_admin') && $userTeamIds->contains($numberRange->team_id);
    }
}
