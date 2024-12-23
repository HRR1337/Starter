<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin') && $user->can('view_any_shield::role');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \Spatie\Permission\Models\Role  $role
     * @return bool
     */
    public function view(User $user, Role $role): bool
    {
        return $user->hasRole('super_admin') && $user->can('view_shield::role');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasRole('super_admin') && $user->can('create_shield::role');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \Spatie\Permission\Models\Role  $role
     * @return bool
     */
    public function update(User $user, Role $role): bool
    {
        return $user->hasRole('super_admin') && $user->can('update_shield::role');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \Spatie\Permission\Models\Role  $role
     * @return bool
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->hasRole('super_admin') && $user->can('delete_shield::role');
    }

    /**
     * Determine whether the user can bulk delete.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasRole('super_admin') && $user->can('delete_any_shield::role');
    }

    /**
     * Determine whether the user can permanently delete.
     *
     * @param  \App\Models\User  $user
     * @param  \Spatie\Permission\Models\Role  $role
     * @return bool
     */
    public function forceDelete(User $user, Role $role): bool
    {
        return $user->hasRole('super_admin') && $user->can('force_delete_shield::role');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('super_admin') && $user->can('force_delete_any_shield::role');
    }

    /**
     * Determine whether the user can restore.
     *
     * @param  \App\Models\User  $user
     * @param  \Spatie\Permission\Models\Role  $role
     * @return bool
     */
    public function restore(User $user, Role $role): bool
    {
        return $user->hasRole('super_admin') && $user->can('restore_shield::role');
    }

    /**
     * Determine whether the user can bulk restore.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function restoreAny(User $user): bool
    {
        return $user->hasRole('super_admin') && $user->can('restore_any_shield::role');
    }

    /**
     * Determine whether the user can replicate.
     *
     * @param  \App\Models\User  $user
     * @param  \Spatie\Permission\Models\Role  $role
     * @return bool
     */
    public function replicate(User $user, Role $role): bool
    {
        return $user->hasRole('super_admin') && $user->can('replicate_shield::role');
    }

    /**
     * Determine whether the user can reorder.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function reorder(User $user): bool
    {
        return $user->hasRole('super_admin') && $user->can('reorder_shield::role');
    }
}
