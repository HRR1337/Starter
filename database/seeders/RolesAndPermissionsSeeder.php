<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Definieer ALLE permissions
        $permissions = [

            // Number Range permissions
            'view_any_number_range',
            'view_number_range',
            'create_number_range',
            'update_number_range',
            'delete_number_range',
            'delete_any_number_range',

            // Team permissions
            'view_any_team',
            'view_team',
            'create_team',
            'update_team',
            'delete_team',
            'delete_any_team',
            'force_delete_team',
            'force_delete_any_team',
            'restore_team',
            'restore_any_team',
            'replicate_team',
            'reorder_team',

            // User permissions
            'view_any_user',
            'view_user',
            'create_user',
            'update_user',
            'delete_user',
            'delete_any_user',
            'force_delete_user',
            'force_delete_any_user',
            'restore_user',
            'restore_any_user',
            'replicate_user',
            'reorder_user',

            // Exception permissions
            'view_any_exception',
            'view_exception',
            'create_exception',
            'update_exception',
            'delete_exception',
            'delete_any_exception',
            'force_delete_exception',
            'force_delete_any_exception',
            'restore_exception',
            'restore_any_exception',
            'replicate_exception',
            'reorder_exception',

            // Route Statistics permissions
            'view_any_route::statistics',
            'view_route::statistics',
            'create_route::statistics',
            'update_route::statistics',
            'delete_route::statistics',
            'delete_any_route::statistics',
            'force_delete_route::statistics',
            'force_delete_any_route::statistics',
            'restore_route::statistics',
            'restore_any_route::statistics',
            'replicate_route::statistics',
            'reorder_route::statistics',

            // Shield Role permissions
            'view_any_shield::role',
            'view_shield::role',
            'create_shield::role',
            'update_shield::role',
            'delete_shield::role',
            'delete_any_shield::role',
            'force_delete_shield::role',
            'restore_shield::role',
            'restore_any_shield::role',
            'replicate_shield::role',
            'reorder_shield::role',

            // Additional management permissions
            'manage_roles',
            'manage_permissions',
            'manage_settings',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles
        $superAdminRole = Role::create(['name' => 'super_admin']);
        $teamAdminRole = Role::create(['name' => 'team_admin']);
        $teamMemberRole = Role::create(['name' => 'team_member']);

        // Give super admin ALL permissions
        $superAdminRole->syncPermissions(Permission::all());

        // Team Admin permissions
        $teamAdminPermissions = [
            'view_any_team',
            'create_team',
            'update_team',
            'view_any_user',
            'create_user',
            'update_user',
            'delete_user',
            'view_team',
            'delete_team',
            'view_any_number_range',
            'view_number_range',
        ];
        $teamAdminRole->syncPermissions($teamAdminPermissions);

        // Team Member permissions
        $teamMemberPermissions = [
            'view_team',
            'update_user',
            'view_user',
        ];
        $teamMemberRole->syncPermissions($teamMemberPermissions);

        // Create Super Admin account
        $superAdmin = User::create([
            'name' => 'Biblefactory Admin',
            'email' => 'hrr@hrr.nu',
            'password' => Hash::make('password'),
        ]);
        $superAdmin->assignRole($superAdminRole);

        // Create Team Admin account
        $teamAdmin = User::create([
            'name' => 'Team Admin',
            'email' => 'teamadmin@hrr.nu',
            'password' => Hash::make('password'),
        ]);
        $teamAdmin->assignRole($teamAdminRole);

        // Create teams
        $team1 = Team::create([
            'name' => 'team1',
            'slug' => 'team1',
            'created_by' => $teamAdmin->id, // Team1 aangemaakt door Team Admin
        ]);

        $teamBiblefactory = Team::create([
            'name' => 'Biblefactory',
            'slug' => 'biblefactory',
            'created_by' => $superAdmin->id, // Biblefactory aangemaakt door Super Admin
        ]);

        // Assign Team Admin to team
        $team1->users()->attach($teamAdmin);

        // Create Team Member account
        $teamMember = User::create([
            'name' => 'Team Member',
            'email' => 'teammember@hrr.nu',
            'password' => Hash::make('password'),
        ]);
        $teamMember->assignRole($teamMemberRole);

        // Assign Team Member to team
        $team1->users()->attach($teamMember);

        // Assign Super Admin to all teams
        $superAdmin->teams()->attach([$team1->id, $teamBiblefactory->id]);
    }
}
