<?php

namespace Database\Seeders;

use App\Modules\Core\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Ensure admin role exists for Spatie permission checks.
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'api']
        );

        $permissions = [
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'plants.view',
            'plants.create',
            'plants.edit',
            'plants.delete',
            'chemicals.view',
            'chemicals.create',
            'chemicals.edit',
            'chemicals.delete',
            'equipment.view',
            'equipment.create',
            'equipment.edit',
            'equipment.delete',
            'achievements.view',
            'achievements.create',
            'achievements.edit',
            'achievements.delete',
            'transactions.view',
            'reports.view',
            'manage-roles',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }

        $adminRole->syncPermissions($permissions);

        $user = User::where('email', 'admin@example.com')->first();

        if (! $user) {
            $user = User::factory()->create([
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('Admin@123'),
                'role' => 'admin',
            ]);
        } else {
            $user->update(['name' => 'Admin', 'role' => 'admin']);
        }

        // Assign the Spatie role to user so middleware hasRole('admin') works.
        $user->assignRole($adminRole);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
