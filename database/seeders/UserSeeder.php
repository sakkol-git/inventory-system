<?php

namespace Database\Seeders;

use App\Modules\Core\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Ensure admin role exists for Spatie permission checks.
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'api']
        );

        $permissions = [
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
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
    }
}
